<?php

namespace App\Models;

use App\Support\Caching\PublicReadCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => PublicReadCache::flushCatalog());
        static::deleted(fn () => PublicReadCache::flushCatalog());
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }
}
