<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AdCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'event_id',
        'user_id',
        'title',
        'slug',
        'headline',
        'body',
        'destination_url',
        'creative_image',
        'placement',
        'budget_amount',
        'budget_currency',
        'targeting_json',
        'popup_settings_json',
        'start_at',
        'end_at',
        'status',
        'published_at',
        'package_expires_at',
        'active_subscription_id',
        'impressions',
        'clicks',
    ];

    protected function casts(): array
    {
        return [
            'start_at'    => 'datetime',
            'end_at'      => 'datetime',
            'published_at' => 'datetime',
            'package_expires_at' => 'datetime',
            'budget_amount' => 'decimal:2',
            'targeting_json' => 'array',
            'popup_settings_json' => 'array',
            'impressions' => 'integer',
            'clicks'      => 'integer',
        ];
    }

    public function ctr(): float
    {
        return $this->impressions > 0
            ? round(($this->clicks / $this->impressions) * 100, 2)
            : 0.0;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orderItems(): MorphMany
    {
        return $this->morphMany(OrderItem::class, 'purchasable');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscribable_id')
            ->where('subscribable_type', self::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'id', 'active_subscription_id');
    }

    public function linkedListingHasActiveEntitlement(): bool
    {
        return $this->listing?->hasActiveBusinessEntitlement() ?? false;
    }

    public function hasActiveAdvertEntitlement(): bool
    {
        return $this->activeSubscription()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->exists();
    }

    public function isOperational(): bool
    {
        return $this->status === 'active'
            && $this->linkedListingHasActiveEntitlement()
            && $this->hasActiveAdvertEntitlement();
    }
}
