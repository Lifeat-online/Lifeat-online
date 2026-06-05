<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use App\Support\Caching\PublicReadCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
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

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_tag');
    }
}
