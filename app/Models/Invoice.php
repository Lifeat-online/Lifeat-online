<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'invoice_prefix_snapshot',
        'status',
        'currency',
        'subtotal',
        'vat_amount',
        'total',
        'issued_at',
        'due_at',
        'emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function markEmailed(?Carbon $at = null): void
    {
        $this->update([
            'status' => $this->status === 'draft' ? 'issued' : $this->status,
            'issued_at' => $this->issued_at ?: ($at ?? now()),
            'emailed_at' => $at ?? now(),
        ]);
    }
}
