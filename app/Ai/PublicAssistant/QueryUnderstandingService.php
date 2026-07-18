<?php

namespace App\Ai\PublicAssistant;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class QueryUnderstandingService
{
    public function understand(string $question, ?User $user = null, array $context = []): array
    {
        $context = $this->normalizeContext($context);
        $locale = $this->targetLocale($question, $user, $context);
        $context['locale'] = $locale;

        return [
            'context' => $context,
            'locale' => $locale,
            'search' => $this->searchContext($question, $context),
        ];
    }

    public function publicSearchContext(array $search): array
    {
        return [
            'base_terms' => $search['base_terms'] ?? [],
            'location' => $search['location'] ?? null,
            'time_window' => $search['time_window'] ?? null,
            'timezone' => $search['timezone'] ?? 'Africa/Johannesburg',
            'near_me' => (bool) ($search['near_me'] ?? false),
        ];
    }

    public function localeName(string $locale): string
    {
        return (string) data_get(config('localization.supported'), "{$locale}.name", $locale);
    }

    public function languageInstruction(string $locale): string
    {
        return $locale === 'af'
            ? 'Answer in natural Afrikaans. Use the product name Ask Life. Keep Life@, business names, routes, URLs, and official place names unchanged where appropriate.'
            : 'Answer in natural South African English. Keep Life@, business names, routes, URLs, and official place names unchanged where appropriate.';
    }

    private function normalizeContext(array $context): array
    {
        $allowed = ['page_type', 'page_title', 'page_heading', 'page_url', 'path', 'timezone', 'local_time', 'locale'];
        $clean = [];
        foreach ($allowed as $key) {
            $value = trim((string) ($context[$key] ?? ''));
            if ($value !== '') {
                $clean[$key] = Str::limit($value, $key === 'page_url' ? 2048 : 220, '');
            }
        }
        $clean['page_type'] ??= $this->inferPageType((string) ($clean['path'] ?? ''), (string) ($clean['page_url'] ?? ''));
        $clean['timezone'] = $this->validTimezone((string) ($clean['timezone'] ?? 'Africa/Johannesburg'));

        return $clean;
    }

    private function inferPageType(string $path, string $url): string
    {
        $target = mb_strtolower($path.' '.$url);

        return match (true) {
            str_contains($target, '/account/listings') => 'account_listing_workspace',
            str_contains($target, '/account/advertising') => 'account_advertising',
            str_contains($target, '/account') => 'account',
            str_contains($target, '/directory/') => 'business_detail',
            str_contains($target, '/directory') => 'directory',
            str_contains($target, '/events/') => 'event_detail',
            str_contains($target, '/events') => 'events',
            str_contains($target, '/articles/') => 'article_detail',
            str_contains($target, '/articles') => 'articles',
            str_contains($target, '/vouchers') => 'vouchers',
            str_contains($target, '/classifieds') || str_contains($target, '/my-classifieds') => 'classifieds',
            str_contains($target, '/faults') => 'faults',
            str_contains($target, '/transport') => 'transport',
            str_contains($target, '/advertise') => 'advertise',
            str_contains($target, '/add-listing') => 'add_listing',
            str_contains($target, '/checkout') || str_contains($target, '/basket') => 'checkout',
            default => 'general',
        };
    }

    private function targetLocale(string $question, ?User $user, array $context): string
    {
        $preferred = [];
        foreach ([$user?->preferred_locale, $context['locale'] ?? null, app()->getLocale()] as $locale) {
            $normalized = $this->normalizeLocale($locale);
            if ($normalized === 'af') {
                return 'af';
            }
            if ($normalized !== null) {
                $preferred[] = $normalized;
            }
        }

        return $this->detectLocale($question) === 'af' ? 'af' : ($preferred[0] ?? 'en');
    }

    private function normalizeLocale(?string $locale): ?string
    {
        $locale = str_replace('_', '-', mb_strtolower(trim((string) $locale)));
        if ($locale === '') {
            return null;
        }
        $locale = explode('-', $locale)[0] ?: $locale;

        return array_key_exists($locale, (array) config('localization.supported', [])) ? $locale : null;
    }

    private function searchContext(string $question, array $context): array
    {
        $baseTerms = $this->terms($question.' '.($context['page_heading'] ?? ''));
        $location = $this->detectLocation($question.' '.($context['page_heading'] ?? ''));
        $timezone = $this->validTimezone((string) ($context['timezone'] ?? 'Africa/Johannesburg'));
        $expanded = $this->expandedTerms($baseTerms);
        if ($location) {
            $expanded[] = mb_strtolower($location);
        }

        return [
            'base_terms' => $baseTerms,
            'terms' => collect($expanded)->filter()->unique()->take(18)->values()->all(),
            'location' => $location,
            'time_window' => $this->detectTimeWindow($question, $timezone),
            'timezone' => $timezone,
            'near_me' => str_contains(' '.mb_strtolower($question).' ', ' near me ') || str_contains(' '.mb_strtolower($question).' ', ' naby my '),
        ];
    }

    private function terms(string $question): array
    {
        $normalized = mb_strtolower((string) preg_replace('/[^\pL\pN]+/u', ' ', $question));
        $stopWords = ['and', 'the', 'for', 'with', 'near', 'from', 'that', 'this', 'what', 'where', 'when', 'who', 'are', 'is', 'was', 'were', 'can', 'you', 'please', 'need', 'find', 'show', 'open', 'life', 'wat', 'waar', 'wie', 'die', 'met', 'van', 'vir', 'het', 'kan', 'asseblief'];

        return collect(preg_split('/\s+/', $normalized) ?: [])->filter(fn (string $term): bool => mb_strlen($term) >= 3 && ! in_array($term, $stopWords, true))->unique()->take(10)->values()->all();
    }

    private function expandedTerms(array $baseTerms): array
    {
        $map = [
            'mechanic' => ['auto', 'vehicle', 'car', 'repair', 'garage', 'workshop', 'service'],
            'tyre' => ['tire', 'wheel', 'puncture', 'alignment', 'mechanic', 'auto'],
            'tire' => ['tyre', 'wheel', 'puncture', 'alignment', 'mechanic', 'auto'],
            'restaurant' => ['food', 'eat', 'dinner', 'lunch', 'coffee', 'takeaway'],
            'coffee' => ['cafe', 'restaurant', 'breakfast', 'food'],
            'doctor' => ['medical', 'clinic', 'health', 'gp'],
            'dentist' => ['dental', 'teeth', 'medical', 'health'],
            'plumber' => ['water', 'pipe', 'leak', 'repair'],
            'electrician' => ['electrical', 'power', 'wiring', 'repair'],
            'fault' => ['report', 'pothole', 'water', 'electricity', 'streetlight', 'dumping'],
            'pothole' => ['road', 'fault', 'report', 'slaggat'],
            'water' => ['leak', 'pipe', 'fault', 'municipal'],
            'event' => ['events', 'weekend', 'market', 'festival', 'show'],
            'voucher' => ['deal', 'special', 'discount', 'offer'],
            'classified' => ['sale', 'buy', 'sell', 'marketplace'],
            'transport' => ['taxi', 'ride', 'delivery', 'parcel', 'moving'],
            'website' => ['web', 'developer', 'developers', 'design', 'digital', 'software', 'online', 'ecommerce'],
            'developer' => ['developers', 'website', 'web', 'software', 'digital', 'online'],
            'hotel' => ['accommodation', 'b&b', 'bnb', 'guesthouse', 'guest', 'lodge', 'overnight', 'stay'],
            'accommodation' => ['hotel', 'b&b', 'bnb', 'guesthouse', 'guest', 'lodge', 'self catering', 'overnight'],
        ];
        $expanded = $baseTerms;
        foreach ($baseTerms as $term) {
            foreach ($map as $key => $synonyms) {
                if ($term === $key || in_array($term, $synonyms, true)) {
                    $expanded = array_merge($expanded, [$key], $synonyms);
                }
            }
        }

        return $expanded;
    }

    private function detectLocation(string $text): ?string
    {
        $normalized = ' '.mb_strtolower((string) preg_replace('/[^\pL\pN]+/u', ' ', $text)).' ';
        foreach (['Bethlehem', 'Harrismith', 'Clarens', 'Reitz', 'Kestell', 'Fouriesburg', 'Ficksburg', 'Ladybrand', 'Phuthaditjhaba', 'Qwaqwa', 'Warden', 'Frankfort', 'Vrede', 'Lindley', 'Senekal', 'Rosendal', 'Arlington', 'Paul Roux', 'Golden Gate', 'Eastern Free State', 'Freestate', 'Free State'] as $location) {
            if (str_contains($normalized, ' '.mb_strtolower($location).' ')) {
                return $location;
            }
        }

        return null;
    }

    private function detectTimeWindow(string $question, string $timezone): ?array
    {
        $normalized = ' '.mb_strtolower($question).' ';
        $now = CarbonImmutable::now($timezone);
        if ($this->containsAny($normalized, [' today ', ' vandag '])) {
            return ['label' => 'today', 'start' => $now->startOfDay()->toDateTimeString(), 'end' => $now->endOfDay()->toDateTimeString()];
        }
        if ($this->containsAny($normalized, [' tomorrow ', ' more '])) {
            $day = $now->addDay();

            return ['label' => 'tomorrow', 'start' => $day->startOfDay()->toDateTimeString(), 'end' => $day->endOfDay()->toDateTimeString()];
        }
        if ($this->containsAny($normalized, [' tonight ', ' vanaand '])) {
            return ['label' => 'tonight', 'start' => $now->toDateTimeString(), 'end' => $now->endOfDay()->toDateTimeString()];
        }
        if ($this->containsAny($normalized, [' weekend ', ' this weekend ', ' naweek '])) {
            $saturday = $now->isSaturday() ? $now : ($now->isSunday() ? $now->subDay() : $now->next(CarbonImmutable::SATURDAY));

            return ['label' => 'this weekend', 'start' => $saturday->startOfDay()->toDateTimeString(), 'end' => $saturday->addDay()->endOfDay()->toDateTimeString()];
        }
        if ($this->containsAny($normalized, [' next week ', ' volgende week '])) {
            $monday = $now->next(CarbonImmutable::MONDAY);

            return ['label' => 'next week', 'start' => $monday->startOfDay()->toDateTimeString(), 'end' => $monday->endOfWeek()->endOfDay()->toDateTimeString()];
        }

        return null;
    }

    private function detectLocale(string $text): string
    {
        $combined = ' '.mb_strtolower($text).' ';
        foreach ([' asseblief ', ' dankie ', ' waar ', ' wanneer ', ' hoekom ', ' hoeveel ', ' naby ', ' besigheid ', ' geleentheid ', ' fout ', ' krag ', ' pad ', ' slaggat ', ' vandag ', ' hierdie ', ' soek ', ' help my ', ' is daar '] as $marker) {
            if (str_contains($combined, $marker)) {
                return 'af';
            }
        }

        return 'en';
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        return collect($needles)->contains(fn (string $needle): bool => $needle !== '' && str_contains($haystack, $needle));
    }

    private function validTimezone(string $timezone): string
    {
        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Africa/Johannesburg';
    }
}
