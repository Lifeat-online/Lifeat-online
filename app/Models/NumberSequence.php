<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NumberSequence extends Model
{
    protected $fillable = [
        'key',
        'prefix',
        'year',
        'last_value',
        'padding',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_value' => 'integer',
            'padding' => 'integer',
        ];
    }

    public static function next(string $key, ?string $prefix = null, int $padding = 6, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        $sequence = DB::transaction(function () use ($key, $prefix, $padding, $year) {
            $sequence = static::query()
                ->where('key', $key)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = static::query()->create([
                    'key' => $key,
                    'prefix' => $prefix,
                    'year' => $year,
                    'padding' => $padding,
                    'last_value' => 0,
                ]);
            }

            $sequence->last_value += 1;
            $sequence->save();

            return $sequence;
        });

        $number = str_pad((string) $sequence->last_value, $sequence->padding, '0', STR_PAD_LEFT);

        if ($sequence->prefix) {
            return $sequence->prefix.'-'.$sequence->year.'-'.$number;
        }

        return $sequence->year.'-'.$number;
    }
}
