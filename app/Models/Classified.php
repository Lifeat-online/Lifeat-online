<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Classified extends Model
{
    use HasFactory, HasContentTranslations;

    protected array $translatable = [
        'title',
        'description',
        'city',
        'region',
        'country',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN = 'hidden';
    public const STATUS_FLAGGED = 'flagged';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'reviewed_by_user_id',
        'title',
        'slug',
        'description',
        'price',
        'currency',
        'contact_for_price',
        'featured_image',
        'city',
        'region',
        'country',
        'latitude',
        'longitude',
        'status',
        'submitted_at',
        'reviewed_at',
        'moderation_notes',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'contact_for_price' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public static function moderationStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PUBLISHED,
            self::STATUS_HIDDEN,
            self::STATUS_FLAGGED,
            self::STATUS_REJECTED,
        ];
    }

    public static function reviewableStatuses(): array
    {
        return [
            self::STATUS_PUBLISHED,
            self::STATUS_HIDDEN,
            self::STATUS_FLAGGED,
            self::STATUS_REJECTED,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
