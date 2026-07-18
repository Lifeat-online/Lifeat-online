<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EditorialClaim extends Model
{
    protected $fillable = ['editorial_dossier_id', 'claim', 'importance', 'status'];

    public function dossier(): BelongsTo { return $this->belongsTo(EditorialDossier::class); }

    public function evidence(): HasMany { return $this->hasMany(ClaimEvidence::class); }
}
