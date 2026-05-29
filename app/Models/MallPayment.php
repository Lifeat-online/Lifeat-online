<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MallPayment extends Model
{
    protected $fillable = [
        'mall_order_id',
        'm_payment_id',
        'payfast_payment_id',
        'amount',
        'status',
        'itn_payload',
        'payfast_fee',
        'net_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'itn_payload' => 'array',
            'payfast_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MallOrder::class, 'mall_order_id');
    }
}
