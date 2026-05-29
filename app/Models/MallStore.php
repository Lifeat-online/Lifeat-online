<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class MallStore extends Model
{
    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'tagline',
        'description',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'logo_path',
        'banner_path',
        'primary_color',
        'payfast_merchant_id',
        'payfast_merchant_key',
        'status',
        'is_featured',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_featured' => 'boolean',
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(MallStoreCategory::class, 'mall_store_category_mall_store');
    }

    public function products(): HasMany
    {
        return $this->hasMany(MallProduct::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(MallProductCategory::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(MallCart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MallOrder::class);
    }

    public function vendorProfile(): HasOne
    {
        return $this->hasOne(MallVendorProfile::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function getFeaturedProducts(int $limit = 6)
    {
        return $this->products()
            ->active()
            ->featured()
            ->limit($limit)
            ->get();
    }

    public function hasPayFastSplit(): bool
    {
        return filled($this->payfast_merchant_id) && filled($this->payfast_merchant_key);
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : asset('branding/life-logo-light.svg');
    }

    public function getBannerUrlAttribute(): string
    {
        return $this->banner_path
            ? Storage::disk('public')->url($this->banner_path)
            : asset('illustrations/directory-burst.svg');
    }
}
