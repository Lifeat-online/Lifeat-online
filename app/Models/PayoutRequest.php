<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutRequest extends Model
{
    use HasFactory;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_PAID      = 'paid';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'wallet_id',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'amount',
        'currency',
        'status',
        'bank_name',
        'account_holder',
        'account_number',
        'branch_code',
        'payment_reference',
        'notes',
        'requested_at',
        'reviewed_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'requested_at' => 'datetime',
            'reviewed_at'  => 'datetime',
            'paid_at'      => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(StaffWallet::class, 'wallet_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class);
    }

    public function isActionable(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_APPROVED], true);
    }

    public static function activeStatuses(): array
    {
        return [self::STATUS_REQUESTED, self::STATUS_APPROVED];
    }
}
