<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'package_id',
        'purchasable_type',
        'purchasable_id',
        'name_snapshot',
        'unit_price',
        'quantity',
        'billing_model',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }
}
