<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AskLifeFeedback extends Model
{
    use HasFactory;

    protected $table = 'ask_life_feedback';

    protected $fillable = [
        'user_id',
        'ai_generation_id',
        'rating',
        'intent',
        'source',
        'question',
        'answer',
        'source_ids',
        'sources',
        'page_context',
        'reason',
        'ip_hash',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'source_ids' => 'array',
            'sources' => 'array',
            'page_context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'ai_generation_id');
    }
}
