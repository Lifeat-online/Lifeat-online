<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimEvidence extends Model
{
    protected $table = 'claim_evidence';
    protected $fillable = ['editorial_claim_id', 'source_snapshot_id', 'stance', 'excerpt', 'authority_score'];

    public function claim(): BelongsTo { return $this->belongsTo(EditorialClaim::class, 'editorial_claim_id'); }

    public function snapshot(): BelongsTo { return $this->belongsTo(SourceSnapshot::class, 'source_snapshot_id'); }
}
