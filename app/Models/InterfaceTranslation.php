<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterfaceTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'locale',
        'source_hash',
        'source_text',
        'translated_text',
        'provider',
        'model',
        'translated_at',
    ];

    protected function casts(): array
    {
        return [
            'translated_at' => 'datetime',
        ];
    }
}
