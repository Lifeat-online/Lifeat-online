<?php

namespace App\Models;

use App\Support\Mall\MallMoney;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class MallProduct extends Model
{
    protected $fillable = [
        'mall_store_id',
        'name',
        'slug',
        'short_description',
        'description',
        'price',
        'compare_price',
        'sku',
        'stock_qty',
        'parcel_weight_kg',
        'manage_stock',
        'is_featured',
        'is_active',
        'images',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'parcel_weight_kg' => 'decimal:3',
            'images' => 'array',
            'meta' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'manage_stock' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MallStore::class, 'mall_store_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(MallProductCategory::class, 'mall_product_mall_product_category');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function isInStock(int $quantity = 1): bool
    {
        return ! $this->manage_stock || $this->stock_qty >= $quantity;
    }

    public function isOnSale(): bool
    {
        return $this->compare_price !== null
            && MallMoney::toCents($this->compare_price) > MallMoney::toCents($this->price);
    }

    public function getMainImageUrlAttribute(): string
    {
        $images = $this->images ?? [];

        return ! empty($images)
            ? Storage::disk('public')->url($images[0])
            : asset('illustrations/community-mosaic.svg');
    }
}
