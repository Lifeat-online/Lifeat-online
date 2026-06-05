<?php

namespace App\Models;

use App\Support\Caching\PublicReadCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'currency',
        'amount',
        'vat_inclusive',
        'effective_from',
        'effective_to',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'vat_inclusive' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => PublicReadCache::flushCatalog());
        static::deleted(fn () => PublicReadCache::flushCatalog());
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
