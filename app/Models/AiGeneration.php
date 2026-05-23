<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiGeneration extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EDITED = 'edited';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'feature_key',
        'source_type',
        'source_id',
        'user_id',
        'provider',
        'model',
        'prompt_version',
        'input_hash',
        'input_summary',
        'input_payload',
        'retry_of_id',
        'output_language',
        'output_payload',
        'status',
        'error_message',
        'token_input_estimate',
        'token_output_estimate',
        'cost_estimate',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'output_payload' => 'array',
            'input_payload' => 'array',
            'cost_estimate' => 'decimal:6',
            'reviewed_at' => 'datetime',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_id');
    }
}
