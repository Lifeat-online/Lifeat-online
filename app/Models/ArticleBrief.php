<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ArticleBrief extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DRAFTED = 'drafted';

    protected $fillable = [
        'research_item_id',
        'ai_generation_id',
        'suggested_category_id',
        'title',
        'angle',
        'source_urls',
        'suggested_tags',
        'locality_score',
        'newsworthiness_score',
        'confidence_score',
        'duplicate_risk',
        'editorial_notes',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_urls' => 'array',
            'suggested_tags' => 'array',
            'locality_score' => 'decimal:2',
            'newsworthiness_score' => 'decimal:2',
            'confidence_score' => 'decimal:2',
            'duplicate_risk' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function researchItem(): BelongsTo
    {
        return $this->belongsTo(ResearchItem::class);
    }

    public function aiGeneration(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class);
    }

    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function article(): HasOne
    {
        return $this->hasOne(Article::class);
    }
}
