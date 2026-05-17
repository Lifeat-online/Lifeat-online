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

class Listing extends Model
{
    use HasFactory, HasContentTranslations;

    protected array $translatable = [
        'title',
        'excerpt',
        'description',
        'address_line',
        'city',
        'region',
        'country',
    ];

    protected $fillable = [
        'user_id',
        'registered_by_user_id',
        'source_channel',
        'title',
        'slug',
        'excerpt',
        'description',
        'website_url',
        'email',
        'phone',
        'address_line',
        'city',
        'region',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'featured_image',
        'logo_path',
        'status',
        'is_featured',
        'published_at',
        'package_expires_at',
        'active_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'package_expires_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'listing_category');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function adCampaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function pushCampaigns(): HasMany
    {
        return $this->hasMany(PushCampaign::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function marketingIntegrations(): HasMany
    {
        return $this->hasMany(MarketingIntegration::class);
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
            });
    }

    public function hasActiveBusinessEntitlement(): bool
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
        return $this->status === 'published' && $this->hasActiveBusinessEntitlement();
    }
}
