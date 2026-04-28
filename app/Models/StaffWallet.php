<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'currency',
        'available_balance',
        'pending_balance',
        'paid_out_total',
    ];

    protected function casts(): array
    {
        return [
            'available_balance' => 'decimal:2',
            'pending_balance'   => 'decimal:2',
            'paid_out_total'    => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class, 'wallet_id');
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class, 'wallet_id');
    }

    public function pendingPayoutRequest(): ?PayoutRequest
    {
        return $this->payoutRequests()
            ->whereIn('status', ['requested', 'approved'])
            ->latest('id')
            ->first();
    }
}
