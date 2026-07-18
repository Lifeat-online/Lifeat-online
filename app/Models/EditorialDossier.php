<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EditorialDossier extends Model
{
    protected $fillable = ['story_cluster_id', 'title', 'summary', 'status', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(StoryCluster::class, 'story_cluster_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(EditorialClaim::class);
    }

    public function readyForWriting(): bool
    {
        return $this->status === 'approved'
            && $this->hasSupportedHighImportanceClaims();
    }

    public function hasSupportedHighImportanceClaims(): bool
    {
        return ! $this->claims()->where('importance', 'high')
            ->whereDoesntHave('evidence', fn ($query) => $query->where('stance', 'supports'))
            ->exists();
    }
}
