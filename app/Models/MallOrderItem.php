<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MallOrderItem extends Model
{
    protected $fillable = [
        'mall_order_id',
        'mall_product_id',
        'product_name',
        'product_sku',
        'quantity',
        'parcel_weight_kg',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'parcel_weight_kg' => 'decimal:3',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MallOrder::class, 'mall_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MallProduct::class, 'mall_product_id');
    }
}
