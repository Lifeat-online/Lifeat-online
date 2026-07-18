<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorMessage extends Model
{
    protected $fillable = ['operator_conversation_id', 'operator_tool_run_id', 'role', 'tool', 'content', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(OperatorConversation::class, 'operator_conversation_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(OperatorToolRun::class, 'operator_tool_run_id');
    }
}
