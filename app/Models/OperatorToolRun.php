<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OperatorToolRun extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['user_id', 'tool', 'risk', 'arguments', 'result', 'status', 'idempotency_key', 'operator_tool_approval_id', 'error'];
    protected function casts(): array { return ['arguments' => 'array', 'result' => 'array']; }
}
