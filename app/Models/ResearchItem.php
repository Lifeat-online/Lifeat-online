<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResearchItem extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_BRIEFED = 'briefed';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'research_source_id',
        'source_name',
        'source_type',
        'source_url',
        'external_id',
        'title',
        'summary',
        'author',
        'raw_payload',
        'published_at',
        'fetched_at',
        'detected_locations',
        'detected_entities',
        'fingerprint',
        'status',
        'duplicate_of_id',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
            'detected_locations' => 'array',
            'detected_entities' => 'array',
        ];
    }

    public function researchSource(): BelongsTo
    {
        return $this->belongsTo(ResearchSource::class);
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function brief(): HasOne
    {
        return $this->hasOne(ArticleBrief::class);
    }
}
