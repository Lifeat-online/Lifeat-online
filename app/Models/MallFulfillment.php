<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MallFulfillment extends Model
{
    protected $fillable = [
        'mall_order_id',
        'provider',
        'label',
        'status',
        'delivery_fee',
        'platform_fee',
        'provider_amount',
        'delivery_area',
        'delivery_address',
        'contact_phone',
        'external_type',
        'external_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'delivery_fee' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'provider_amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MallOrder::class, 'mall_order_id');
    }
}
