<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouncillorArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'councillor_id',
        'name',
        'geojson',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'geojson' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function councillor(): BelongsTo
    {
        return $this->belongsTo(Councillor::class);
    }
}

