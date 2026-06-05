<?php

namespace App\Models;

use App\Events\PaymentPaid;
use App\Services\BusinessDirectoryActivationService;
use App\Services\StaffCommissionService;
use App\Support\Logging\OperationalLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'provider',
        'status',
        'amount',
        'currency',
        'provider_transaction_id',
        'failure_reason',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Payment $payment) {
            if ($payment->wasChanged('status')) {
                OperationalLog::info('payment.status_changed', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'user_id' => $payment->user_id,
                    'provider' => $payment->provider,
                    'previous_status' => $payment->getOriginal('status'),
                    'status' => $payment->status,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'provider_transaction_id' => $payment->provider_transaction_id,
                    'paid_at' => $payment->paid_at,
                ]);
            }

            if ($payment->status === 'paid' && $payment->wasChanged('status')) {
                if (DB::transactionLevel() > 0) {
                    DB::afterCommit(function () use ($payment): void {
                        PaymentPaid::dispatch($payment->fresh());
                        app(BusinessDirectoryActivationService::class)->activateForPayment($payment);
                        app(StaffCommissionService::class)->creditForPayment($payment);
                    });
                } else {
                    PaymentPaid::dispatch($payment->fresh());
                    app(BusinessDirectoryActivationService::class)->activateForPayment($payment);
                    app(StaffCommissionService::class)->creditForPayment($payment);
                }
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }
}
