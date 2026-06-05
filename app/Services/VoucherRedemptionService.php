<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Support\Logging\OperationalLog;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class VoucherRedemptionService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly NotificationDispatchService $notificationDispatchService,
    ) {
    }

    public function claim(Voucher $voucher, ?User $customer): VoucherRedemption
    {
        $created = false;

        $redemption = $this->db->transaction(function () use ($voucher, $customer, &$created) {
            $voucher = Voucher::query()->lockForUpdate()->findOrFail($voucher->id);

            if (! $voucher->isCurrentlyActive()) {
                OperationalLog::warning('voucher.claim_rejected', $this->voucherContext($voucher, [
                    'customer_user_id' => $customer?->id,
                    'rejection_reason' => 'not_currently_active',
                ]));

                throw ValidationException::withMessages([
                    'voucher' => 'This voucher is not currently available.',
                ]);
            }

            if ($customer) {
                $existing = VoucherRedemption::query()
                    ->where('voucher_id', $voucher->id)
                    ->where('user_id', $customer->id)
                    ->where('status', 'claimed')
                    ->latest('id')
                    ->first();

                if ($existing) {
                    OperationalLog::info('voucher.claim_reused', $this->voucherContext($voucher, [
                        'customer_user_id' => $customer->id,
                        'voucher_redemption_id' => $existing->id,
                    ]));

                    return $existing;
                }
            }

            if ($voucher->remainingUses() <= 0) {
                OperationalLog::warning('voucher.claim_rejected', $this->voucherContext($voucher, [
                    'customer_user_id' => $customer?->id,
                    'rejection_reason' => 'usage_limit_reached',
                ]));

                throw ValidationException::withMessages([
                    'voucher' => 'This voucher has reached its usage limit.',
                ]);
            }

            $redemption = VoucherRedemption::create([
                'voucher_id' => $voucher->id,
                'user_id' => $customer?->id,
                'code' => VoucherRedemption::generateUniqueCode(),
                'status' => 'claimed',
                'claimed_at' => now(),
            ]);

            $voucher->increment('redemptions_count');
            $created = true;

            $thresholdPercent = $this->thresholdToNotify($voucher->redemptions_count, $voucher->usage_limit, $voucher->last_usage_threshold_notified);
            if ($thresholdPercent !== null) {
                $voucher->update(['last_usage_threshold_notified' => $thresholdPercent]);

                $this->db->afterCommit(function () use ($voucher, $thresholdPercent) {
                    $voucher->loadMissing('listing.owner');
                    $this->notificationDispatchService->sendVoucherUsageThresholdReached($voucher, $thresholdPercent);
                });
            }

            return $redemption->fresh(['voucher.listing', 'customer']);
        });

        if ($created && $customer?->email) {
            $this->db->afterCommit(function () use ($redemption) {
                $redemption->loadMissing('voucher.listing', 'customer');
                $this->notificationDispatchService->sendVoucherRedeemed($redemption);
            });
        }

        if ($created) {
            OperationalLog::info('voucher.claimed', $this->voucherContext($redemption->voucher, [
                'customer_user_id' => $customer?->id,
                'voucher_redemption_id' => $redemption->id,
            ]));
        }

        return $redemption;
    }

    public function consume(string $code, User $staffUser, string $ip = '', string $userAgent = ''): VoucherRedemption
    {
        $redemption = $this->db->transaction(function () use ($code, $staffUser, $ip, $userAgent) {
            $redemption = VoucherRedemption::query()
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if (! $redemption) {
                OperationalLog::warning('voucher.consume_rejected', [
                    'code_hash' => OperationalLog::hashValue($code),
                    'staff_user_id' => $staffUser->id,
                    'rejection_reason' => 'code_not_found',
                ]);

                throw ValidationException::withMessages([
                    'code' => 'Voucher code not found.',
                ]);
            }

            $redemption->loadMissing('voucher.listing.owner', 'customer');

            if ($redemption->status !== 'claimed') {
                OperationalLog::warning('voucher.consume_rejected', $this->voucherContext($redemption->voucher, [
                    'voucher_redemption_id' => $redemption->id,
                    'customer_user_id' => $redemption->user_id,
                    'staff_user_id' => $staffUser->id,
                    'redemption_status' => $redemption->status,
                    'rejection_reason' => 'not_claimed',
                ]));

                throw ValidationException::withMessages([
                    'code' => 'This voucher has already been used or is no longer valid.',
                ]);
            }

            if ($redemption->voucher->end_at && $redemption->voucher->end_at->isPast()) {
                OperationalLog::warning('voucher.consume_rejected', $this->voucherContext($redemption->voucher, [
                    'voucher_redemption_id' => $redemption->id,
                    'customer_user_id' => $redemption->user_id,
                    'staff_user_id' => $staffUser->id,
                    'rejection_reason' => 'expired',
                ]));

                throw ValidationException::withMessages([
                    'code' => 'This voucher has expired.',
                ]);
            }

            $redemption->update([
                'status' => 'consumed',
                'consumed_at' => now(),
                'consumed_by_user_id' => $staffUser->id,
                'consumed_ip' => $ip !== '' ? $ip : null,
                'consumed_user_agent' => $userAgent !== '' ? mb_substr($userAgent, 0, 500) : null,
            ]);

            return $redemption->fresh(['voucher.listing', 'customer', 'consumedBy']);
        });

        OperationalLog::info('voucher.consumed', $this->voucherContext($redemption->voucher, [
            'voucher_redemption_id' => $redemption->id,
            'customer_user_id' => $redemption->user_id,
            'staff_user_id' => $staffUser->id,
            'consumed_at' => $redemption->consumed_at,
            'code_hash' => OperationalLog::hashValue($code),
        ]));

        return $redemption;
    }

    private function thresholdToNotify(int $used, int $limit, int $lastNotified): ?int
    {
        if ($limit <= 0) {
            return null;
        }

        $percent = (int) floor(($used / $limit) * 100);
        $thresholds = [50, 80, 100];

        $eligible = collect($thresholds)
            ->filter(fn (int $t) => $percent >= $t && $t > $lastNotified)
            ->sortDesc()
            ->first();

        return $eligible ? (int) $eligible : null;
    }

    private function voucherContext(Voucher $voucher, array $extra = []): array
    {
        return array_merge([
            'voucher_id' => $voucher->id,
            'listing_id' => $voucher->listing_id,
            'created_by_user_id' => $voucher->created_by_user_id,
            'status' => $voucher->status,
            'voucher_type' => $voucher->voucher_type,
            'usage_limit' => $voucher->usage_limit,
            'redemptions_count' => $voucher->redemptions_count,
            'start_at' => $voucher->start_at,
            'end_at' => $voucher->end_at,
        ], $extra);
    }
}
