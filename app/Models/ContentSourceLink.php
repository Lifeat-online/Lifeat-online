<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentSourceLink extends Model
{
    protected $fillable = ['source_snapshot_id', 'sourceable_type', 'sourceable_id', 'role'];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SourceSnapshot::class, 'source_snapshot_id');
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }
}
