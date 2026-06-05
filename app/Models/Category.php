<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use App\Support\Caching\PublicReadCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory, HasContentTranslations;

    protected array $translatable = [
        'name',
        'description',
    ];

    protected $fillable = [
        'type',
        'name',
        'slug',
        'description',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => PublicReadCache::flushPublic());
        static::deleted(fn () => PublicReadCache::flushPublic());
    }

    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'listing_category');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_category');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_category');
    }

    public function vouchers(): BelongsToMany
    {
        return $this->belongsToMany(Voucher::class, 'voucher_category');
    }
}
