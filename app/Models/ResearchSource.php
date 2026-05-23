<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchSource extends Model
{
    public const TYPE_GOOGLE_NEWS_RSS = 'google_news_rss';
    public const TYPE_RSS = 'rss';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'url',
        'query',
        'locale',
        'country',
        'is_active',
        'fetch_interval_minutes',
        'last_fetched_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'fetch_interval_minutes' => 'integer',
            'last_fetched_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ResearchItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
