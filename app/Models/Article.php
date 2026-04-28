<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'editor_user_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'featured_image',
        'status',
        'submitted_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_user_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'article_category');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'article_tag');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(LocationNode::class, 'article_locations');
    }

    public function wordLedger(): HasOne
    {
        return $this->hasOne(ArticleWordLedger::class);
    }

    public function revisionNotes(): HasMany
    {
        return $this->hasMany(ArticleRevisionNote::class)->latest();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function wordCount(): int
    {
        $content = $this->body ?: $this->excerpt ?: '';
        $normalized = trim(preg_replace('/\s+/', ' ', strip_tags((string) $content)) ?? '');

        return $normalized === '' ? 0 : Str::wordCount($normalized);
    }
}
