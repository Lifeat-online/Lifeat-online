<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryCluster extends Model
{
    protected $fillable = ['title', 'fingerprint', 'status'];

    public function researchItems(): BelongsToMany
    {
        return $this->belongsToMany(ResearchItem::class);
    }

    public function dossiers(): HasMany
    {
        return $this->hasMany(EditorialDossier::class);
    }
}
