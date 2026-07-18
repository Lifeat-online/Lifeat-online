<?php

namespace App\Models;

use App\Ai\Knowledge\KnowledgeVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends Model
{
    protected $fillable = [
        'source_type', 'source_id', 'locale', 'title', 'canonical_url', 'content',
        'metadata', 'content_hash', 'index_version', 'visibility', 'published_at', 'expires_at', 'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'indexed_at' => 'datetime',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    public function isPublic(): bool
    {
        return $this->visibility === KnowledgeVisibility::PUBLIC
            && $this->published_at !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
