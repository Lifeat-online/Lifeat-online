<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
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
                    return $existing;
                }
            }

            if ($voucher->remainingUses() <= 0) {
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

        return $redemption;
    }

    public function consume(string $code, User $staffUser, string $ip = '', string $userAgent = ''): VoucherRedemption
    {
        return $this->db->transaction(function () use ($code, $staffUser, $ip, $userAgent) {
            $redemption = VoucherRedemption::query()
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if (! $redemption) {
                throw ValidationException::withMessages([
                    'code' => 'Voucher code not found.',
                ]);
            }

            $redemption->loadMissing('voucher.listing.owner', 'customer');

            if ($redemption->status !== 'claimed') {
                throw ValidationException::withMessages([
                    'code' => 'This voucher has already been used or is no longer valid.',
                ]);
            }

            if ($redemption->voucher->end_at && $redemption->voucher->end_at->isPast()) {
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
}
