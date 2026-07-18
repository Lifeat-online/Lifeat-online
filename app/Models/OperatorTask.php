<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperatorTask extends Model
{
    use HasUuids;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_RUNNING = 'running';

    public const STATUS_WAITING_FOR_INPUT = 'waiting_for_input';

    public const STATUS_WAITING_FOR_APPROVAL = 'waiting_for_approval';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'operator_conversation_id', 'user_id', 'goal', 'status', 'plan', 'sources', 'usage',
        'result', 'error', 'step_limit', 'started_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'plan' => 'array', 'sources' => 'array', 'usage' => 'array', 'result' => 'array',
            'started_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(OperatorConversation::class, 'operator_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(OperatorTaskStep::class)->orderBy('position');
    }
}
