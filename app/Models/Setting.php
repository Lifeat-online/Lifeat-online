<?php

namespace App\Models;

use App\Support\Caching\PublicReadCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'updated_by_user_id',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    protected static function booted(): void
    {
        static::saved(fn () => PublicReadCache::flushSettings());
        static::deleted(fn () => PublicReadCache::flushSettings());
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return PublicReadCache::settingValue($key, $default);
    }

    public static function grouped(): Collection
    {
        return static::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group');
    }
}
