<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiManagerAction extends Model
{
    use HasFactory;

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_EXECUTED = 'executed';

    public const MODE_OBSERVER = 'observer';
    public const MODE_APPROVAL = 'approval';
    public const MODE_BUDGETED = 'budgeted';
    public const MODE_AUTONOMOUS = 'autonomous';

    protected $fillable = [
        'action_key',
        'domain',
        'action_type',
        'title',
        'summary',
        'rationale',
        'status',
        'risk_level',
        'required_mode',
        'impact_score',
        'confidence_score',
        'estimated_cost',
        'expected_value',
        'source_type',
        'source_id',
        'payload',
        'proposed_by',
        'reviewed_by',
        'reviewed_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'impact_score' => 'decimal:2',
            'confidence_score' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'expected_value' => 'decimal:2',
            'payload' => 'array',
            'reviewed_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
