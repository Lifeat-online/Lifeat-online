<?php

namespace App\Support\Mall;

class MallMoney
{
    public static function toCents(string|int|null $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        if ($value === '') {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D/', '', $fraction) ?: '0', 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    public static function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        return ($negative ? '-' : '').$whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    public static function multiply(string|int|null $amount, int $quantity): string
    {
        return self::formatCents(self::toCents($amount) * $quantity);
    }

    public static function add(iterable $amounts): string
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $total += self::toCents($amount);
        }

        return self::formatCents($total);
    }

    public static function percent(string|int|null $amount, string|int|null $percent): string
    {
        $cents = self::toCents($amount);
        $basisPoints = self::percentToBasisPoints($percent);

        return self::formatCents(intdiv(($cents * $basisPoints) + 5000, 10000));
    }

    public static function subtract(string|int|null $left, string|int|null $right): string
    {
        return self::formatCents(self::toCents($left) - self::toCents($right));
    }

    private static function percentToBasisPoints(string|int|null $percent): int
    {
        $value = trim((string) ($percent ?? '0'));
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D/', '', $fraction) ?: '0', 2, '0'), 0, 2);
        $basisPoints = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$basisPoints : $basisPoints;
    }
}
