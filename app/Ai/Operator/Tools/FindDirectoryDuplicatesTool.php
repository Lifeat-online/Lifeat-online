<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Str;

class FindDirectoryDuplicatesTool implements OperatorTool
{
    public function name(): string
    {
        return 'directory.find_duplicates';
    }

    public function risk(): string
    {
        return 'R0';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url:https', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return 'read-only';
    }

    public function execute(User $user, array $arguments): array
    {
        $needle = Str::lower(Str::squish($arguments['title']));
        $host = $this->host($arguments['website_url'] ?? null);
        $matches = Listing::query()->get()->map(function (Listing $listing) use ($arguments, $needle, $host): ?array {
            $reasons = [];
            if (Str::lower(Str::squish($listing->title)) === $needle) {
                $reasons[] = 'same_name';
            }
            if ($host && $this->host($listing->website_url) === $host) {
                $reasons[] = 'same_website';
            }
            if (! empty($arguments['email']) && Str::lower((string) $listing->email) === Str::lower($arguments['email'])) {
                $reasons[] = 'same_email';
            }
            if (! empty($arguments['city']) && Str::lower((string) $listing->city) === Str::lower($arguments['city'])) {
                $reasons[] = 'same_city';
            }

            return $reasons === [] ? null : ['listing_id' => $listing->id, 'title' => $listing->title, 'slug' => $listing->slug, 'status' => $listing->status, 'reasons' => $reasons];
        })->filter()->take(10)->values()->all();

        return ['duplicate' => collect($matches)->contains(fn (array $match): bool => in_array('same_website', $match['reasons'], true) || in_array('same_email', $match['reasons'], true) || count($match['reasons']) >= 2), 'matches' => $matches];
    }

    private function host(?string $url): ?string
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));

        return $host ? preg_replace('/^www\./', '', $host) : null;
    }
}
