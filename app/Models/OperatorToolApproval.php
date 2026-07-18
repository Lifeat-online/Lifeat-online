<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OperatorToolApproval extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['approved_by', 'tool', 'risk', 'arguments_hash', 'record_version', 'signature', 'expires_at', 'used_at'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'used_at' => 'datetime']; }
}
