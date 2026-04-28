<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_type_id',
        'name',
        'slug',
        'description',
        'billing_model',
        'is_self_service',
        'duration_days',
        'status',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'is_self_service' => 'boolean',
            'settings_json' => 'array',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PackageType::class, 'package_type_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PackagePrice::class);
    }

    public function currentPrice(): ?PackagePrice
    {
        return $this->prices()
            ->where(function ($query) {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            })
            ->latest('effective_from')
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function entitlementCode(): string
    {
        return (string) ($this->settings_json['entitlement_code'] ?? $this->type?->slug ?? 'package');
    }
}
