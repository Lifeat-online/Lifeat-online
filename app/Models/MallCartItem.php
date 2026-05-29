<?php

namespace App\Models;

use App\Support\Mall\MallMoney;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MallCartItem extends Model
{
    protected $fillable = [
        'mall_cart_id',
        'mall_product_id',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(MallCart::class, 'mall_cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MallProduct::class, 'mall_product_id');
    }

    public function getLineTotalAttribute(): string
    {
        return MallMoney::multiply($this->unit_price, $this->quantity);
    }
}
