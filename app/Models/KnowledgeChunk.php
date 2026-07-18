<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'knowledge_document_id', 'position', 'content', 'content_hash', 'token_count', 'character_count',
        'embedding', 'embedding_provider', 'embedding_model', 'embedding_dimensions', 'embedded_at',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'embedded_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }
}
