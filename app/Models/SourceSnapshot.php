<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceSnapshot extends Model
{
    protected $fillable = ['research_item_id', 'url', 'http_status', 'content_type', 'content', 'content_hash', 'response_headers', 'fetch_error', 'fetched_at'];

    protected function casts(): array
    {
        return ['response_headers' => 'array', 'fetched_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Source snapshots are immutable.'));
        static::deleting(fn () => throw new \LogicException('Source snapshots are immutable outside parent retention deletion.'));
    }

    public function researchItem(): BelongsTo
    {
        return $this->belongsTo(ResearchItem::class);
    }
}
