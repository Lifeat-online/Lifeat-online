<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'locale',
        'content',
        'source_locale',
        'source_hash',
        'provider',
        'model',
        'translated_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'translated_at' => 'datetime',
        ];
    }

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
