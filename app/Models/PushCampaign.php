<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PushCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'event_id',
        'user_id',
        'title',
        'slug',
        'headline',
        'message',
        'budget_amount',
        'budget_currency',
        'schedule_at',
        'audience_scope',
        'target_city',
        'target_region',
        'radius_km',
        'status',
        'published_at',
        'sent_at',
        'package_expires_at',
        'active_subscription_id',
        'open_count',
    ];

    protected function casts(): array
    {
        return [
            'schedule_at'       => 'datetime',
            'published_at'      => 'datetime',
            'sent_at'           => 'datetime',
            'package_expires_at' => 'datetime',
            'budget_amount' => 'decimal:2',
            'open_count'        => 'integer',
        ];
    }

    public function openRate(): float
    {
        $sent = $this->notificationLogs()->where('channel', 'push')->count();
        return $sent > 0
            ? round(($this->open_count / $sent) * 100, 2)
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

    public function notificationLogs(): MorphMany
    {
        return $this->morphMany(NotificationLog::class, 'notifiable');
    }

    public function trackingEvents(): MorphMany
    {
        return $this->morphMany(CampaignTrackingEvent::class, 'trackable');
    }

    public function linkedListingHasActiveEntitlement(): bool
    {
        return $this->listing?->hasActiveBusinessEntitlement() ?? false;
    }

    public function hasActivePushEntitlement(): bool
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
        if ($this->sent_at) {
            return false;
        }

        return in_array($this->status, ['active', 'scheduled'], true)
            && $this->linkedListingHasActiveEntitlement()
            && $this->hasActivePushEntitlement();
    }

    public function audienceSummary(): string
    {
        return match ($this->audience_scope) {
            'listing_region' => 'Listing region: '.($this->target_region ?: $this->listing?->region ?: 'unspecified'),
            'custom_radius' => 'Custom radius: '.($this->radius_km ?: 0).' km around '.($this->target_city ?: $this->listing?->city ?: 'the listing area'),
            default => 'Listing city: '.($this->target_city ?: $this->listing?->city ?: 'unspecified'),
        };
    }
}
