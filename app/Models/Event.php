<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Event extends Model
{
    use HasFactory, HasContentTranslations;

    protected array $translatable = [
        'title',
        'excerpt',
        'description',
        'venue_name',
        'address_line',
        'city',
        'region',
        'country',
    ];

    protected $fillable = [
        'listing_id',
        'user_id',
        'title',
        'slug',
        'excerpt',
        'description',
        'venue_name',
        'address_line',
        'city',
        'region',
        'country',
        'postal_code',
        'start_at',
        'end_at',
        'is_all_day',
        'website_url',
        'featured_image',
        'status',
        'published_at',
        'package_expires_at',
        'active_subscription_id',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'published_at' => 'datetime',
            'package_expires_at' => 'datetime',
            'is_all_day' => 'boolean',
            'latitude'  => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'event_category');
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), 'published')
            ->whereHas('activeSubscription', function ($subscription) {
                $subscription->where('status', 'active')
                    ->where(function ($query) {
                        $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                    });
            })
            ->whereHas('listing.activeSubscription', function ($subscription) {
                $subscription->where('status', 'active')
                    ->where(function ($query) {
                        $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                    });
            });
    }

    public function linkedListingHasActiveEntitlement(): bool
    {
        return $this->listing?->hasActiveBusinessEntitlement() ?? false;
    }

    public function hasActiveEventEntitlement(): bool
    {
        return $this->activeSubscription()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->exists();
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === 'published'
            && $this->hasActiveEventEntitlement()
            && $this->linkedListingHasActiveEntitlement();
    }
}
