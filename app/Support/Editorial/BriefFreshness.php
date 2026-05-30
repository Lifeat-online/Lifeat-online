<?php

namespace App\Support\Editorial;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class BriefFreshness
{
    public static function assess(?CarbonInterface $publishedAt, ?CarbonInterface $asOf = null): array
    {
        $now = self::carbon($asOf ?? now());
        $maxAgeDays = self::maxAgeDays();

        if (! $publishedAt) {
            return [
                'status' => 'undated',
                'label' => 'Undated',
                'score' => 0.0,
                'age_seconds' => null,
                'age_days' => null,
                'age_label' => 'No source date',
                'published_label' => 'Unknown',
                'published_at' => null,
                'approvable' => false,
                'reason' => 'Source has no published date, so it cannot be treated as breaking or fresh news.',
                'max_age_days' => $maxAgeDays,
            ];
        }

        $published = self::carbon($publishedAt)->timezone($now->timezone);
        $ageSeconds = $now->getTimestamp() - $published->getTimestamp();

        if ($ageSeconds < -1 * self::futureToleranceSeconds()) {
            return [
                'status' => 'future',
                'label' => 'Future dated',
                'score' => 0.0,
                'age_seconds' => $ageSeconds,
                'age_days' => 0.0,
                'age_label' => 'Future date',
                'published_label' => $published->format('j M Y H:i'),
                'published_at' => $published,
                'approvable' => false,
                'reason' => 'Source published date is in the future and needs human verification.',
                'max_age_days' => $maxAgeDays,
            ];
        }

        $ageSeconds = max(0, $ageSeconds);
        $ageDays = $ageSeconds / 86400;
        $score = self::scoreForAge($ageSeconds, $maxAgeDays);
        $approvable = $ageDays <= $maxAgeDays;

        return [
            'status' => self::statusForAge($ageSeconds, $maxAgeDays),
            'label' => self::labelForAge($ageSeconds, $maxAgeDays),
            'score' => $score,
            'age_seconds' => $ageSeconds,
            'age_days' => $ageDays,
            'age_label' => self::ageLabel($ageSeconds),
            'published_label' => $published->format('j M Y H:i'),
            'published_at' => $published,
            'approvable' => $approvable,
            'reason' => $approvable
                ? 'Source is inside the fresh-news window.'
                : 'Source is older than '.$maxAgeDays.' days and is too old for a breaking/fresh brief.',
            'max_age_days' => $maxAgeDays,
        ];
    }

    public static function effectiveTimelinessScore(?float $modelScore, array $freshness): float
    {
        $freshnessScore = (float) ($freshness['score'] ?? 0);

        if ($modelScore === null || $modelScore <= 0) {
            return round($freshnessScore, 2);
        }

        return round(min($modelScore, $freshnessScore), 2);
    }

    public static function capNewsworthiness(float $score, array $freshness): float
    {
        $status = (string) ($freshness['status'] ?? 'undated');
        $ceiling = match ($status) {
            'breaking' => 100,
            'fresh' => 90,
            'current_week' => 70,
            default => 20,
        };

        return round(max(0, min($score, $ceiling)), 2);
    }

    public static function approvalMessage(array $freshness): string
    {
        return ((string) ($freshness['reason'] ?? 'Source is not fresh enough.'))
            .' Only reports published in the last '.((int) ($freshness['max_age_days'] ?? self::maxAgeDays())).' days can be approved for Jimmy.';
    }

    public static function appendNote(string $notes, array $freshness): string
    {
        $line = 'Freshness check: '.($freshness['label'] ?? 'Unknown')
            .' - '.($freshness['reason'] ?? 'No freshness reason available.');

        if (str_contains($notes, 'Freshness check:')) {
            return $notes;
        }

        return trim($notes) === '' ? $line : trim($notes)."\n\n".$line;
    }

    public static function policyContext(?CarbonInterface $publishedAt): array
    {
        $freshness = self::assess($publishedAt);

        return [
            'max_age_days' => (int) $freshness['max_age_days'],
            'source_age' => $freshness['age_label'],
            'source_published_at' => $freshness['published_at']?->toDateTimeString(),
            'source_freshness_label' => $freshness['label'],
            'source_freshness_score' => $freshness['score'],
            'approvable' => $freshness['approvable'],
            'rule' => 'Approve or review only reports published in the last '.$freshness['max_age_days'].' days. Older or undated reports must be rejected.',
        ];
    }

    public static function maxAgeDays(): int
    {
        return max(1, (int) config('life_research.brief_max_age_days', 7));
    }

    private static function carbon(CarbonInterface $value): Carbon
    {
        return $value instanceof Carbon
            ? $value->copy()
            : Carbon::instance($value);
    }

    private static function scoreForAge(int $ageSeconds, int $maxAgeDays): float
    {
        $ageHours = $ageSeconds / 3600;
        $ageDays = $ageSeconds / 86400;

        if ($ageHours <= 24) {
            return 100.0;
        }

        if ($ageDays <= 3) {
            return 85.0;
        }

        if ($ageDays <= $maxAgeDays) {
            return 65.0;
        }

        return 0.0;
    }

    private static function statusForAge(int $ageSeconds, int $maxAgeDays): string
    {
        $ageHours = $ageSeconds / 3600;
        $ageDays = $ageSeconds / 86400;

        if ($ageHours <= 24) {
            return 'breaking';
        }

        if ($ageDays <= 3) {
            return 'fresh';
        }

        if ($ageDays <= $maxAgeDays) {
            return 'current_week';
        }

        return 'stale';
    }

    private static function labelForAge(int $ageSeconds, int $maxAgeDays): string
    {
        return match (self::statusForAge($ageSeconds, $maxAgeDays)) {
            'breaking' => 'Breaking',
            'fresh' => 'Fresh',
            'current_week' => 'Current week',
            default => 'Too old',
        };
    }

    private static function ageLabel(int $ageSeconds): string
    {
        if ($ageSeconds < 3600) {
            $minutes = max(1, (int) floor($ageSeconds / 60));

            return $minutes.'m old';
        }

        if ($ageSeconds < 86400) {
            return ((int) floor($ageSeconds / 3600)).'h old';
        }

        if ($ageSeconds < 31536000) {
            return ((int) floor($ageSeconds / 86400)).'d old';
        }

        return ((int) floor($ageSeconds / 31536000)).'y old';
    }

    private static function futureToleranceSeconds(): int
    {
        return max(1, (int) config('life_research.brief_future_tolerance_hours', 6)) * 3600;
    }
}
