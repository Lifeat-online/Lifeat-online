<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class WalletLedgerEntry extends Model
{
    use HasFactory;

    public const TYPE_COMMISSION_CREDIT = 'commission_credit';
    public const TYPE_PAYOUT_DEBIT      = 'payout_debit';
    public const TYPE_ADJUSTMENT        = 'adjustment';

    protected $fillable = [
        'wallet_id',
        'payout_request_id',
        'entry_type',
        'source_type',
        'source_id',
        'gross_amount',
        'net_amount',
        'currency',
        'description',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'net_amount'   => 'decimal:2',
            'recorded_at'  => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Wallet ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Wallet ledger entries are append-only.'));
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(StaffWallet::class, 'wallet_id');
    }

    public function payoutRequest(): BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
