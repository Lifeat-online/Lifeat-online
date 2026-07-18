<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRetrievalTrace extends Model
{
    protected $fillable = ['trace_id', 'query_hash', 'locale', 'lexical_candidates', 'semantic_candidates', 'selected_sources', 'embedding_model', 'embedding_dimensions', 'latency_ms'];
    protected function casts(): array { return ['selected_sources' => 'array']; }
}
