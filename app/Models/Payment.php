<?php

namespace App\Models;

use App\Services\BusinessDirectoryActivationService;
use App\Services\StaffCommissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            if ($payment->status === 'paid' && $payment->wasChanged('status')) {
                app(BusinessDirectoryActivationService::class)->activateForPayment($payment);
                app(StaffCommissionService::class)->creditForPayment($payment);
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
