<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorTaskStep extends Model
{
    protected $fillable = [
        'operator_task_id', 'position', 'action', 'tool', 'risk', 'status', 'arguments', 'result',
        'operator_tool_run_id', 'operator_tool_approval_id', 'error', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array', 'result' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(OperatorTask::class, 'operator_task_id');
    }
}
