<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\Category;
use App\Models\ContentSourceLink;
use App\Models\Listing;
use App\Models\SourceSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDirectoryListingTool implements OperatorTool
{
    public function name(): string
    {
        return 'directory.create_listing';
    }

    public function risk(): string
    {
        return 'R1';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'description' => ['required', 'string', 'max:20000'],
            'website_url' => ['nullable', 'url:https', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'category_names' => ['nullable', 'array', 'max:10'],
            'category_names.*' => ['string', 'max:100'],
            'source_snapshot_ids' => ['required', 'array', 'min:1', 'max:10'],
            'source_snapshot_ids.*' => ['integer', 'distinct', 'exists:source_snapshots,id'],
            'publish' => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return hash('sha256', json_encode([
            'title' => Str::lower($arguments['title']),
            'city' => Str::lower((string) ($arguments['city'] ?? '')),
            'website' => $this->normalisedHost($arguments['website_url'] ?? null),
            'existing' => Listing::query()->max('updated_at')?->getTimestamp(),
        ], JSON_UNESCAPED_SLASHES));
    }

    public function execute(User $user, array $arguments): array
    {
        if ($duplicate = $this->duplicate($arguments)) {
            return [
                'duplicate' => true,
                'listing_id' => $duplicate->id,
                'slug' => $duplicate->slug,
                'status' => $duplicate->status,
                'message' => 'An existing directory listing matches this business.',
            ];
        }

        $snapshots = SourceSnapshot::query()->whereIn('id', $arguments['source_snapshot_ids'])->get();
        $evidence = $this->evidenceAssessment($snapshots, $arguments['website_url'] ?? null);
        $publish = (bool) ($arguments['publish'] ?? true) && $evidence['sufficient'];

        $listing = DB::transaction(function () use ($user, $arguments, $snapshots, $publish): Listing {
            $listing = Listing::create([
                'user_id' => null,
                'registered_by_user_id' => $user->id,
                'source_channel' => 'ai_operator',
                'title' => $arguments['title'],
                'slug' => $this->uniqueSlug($arguments['title']),
                'excerpt' => $arguments['excerpt'] ?? Str::limit($arguments['description'], 300, ''),
                'description' => $arguments['description'],
                'website_url' => $arguments['website_url'] ?? null,
                'email' => $arguments['email'] ?? null,
                'phone' => $arguments['phone'] ?? null,
                'address_line' => $arguments['address_line'] ?? null,
                'city' => $arguments['city'] ?? null,
                'region' => $arguments['region'] ?? null,
                'country' => $arguments['country'] ?? null,
                'postal_code' => $arguments['postal_code'] ?? null,
                'status' => $publish ? 'published' : 'draft',
                'published_at' => $publish ? now() : null,
                'is_featured' => false,
            ]);
            $categoryIds = collect($arguments['category_names'] ?? [])
                ->map(function (string $name): int {
                    $slug = Str::slug($name) ?: 'category-'.substr(hash('sha256', $name), 0, 8);

                    return Category::query()->firstOrCreate(['type' => 'listing', 'slug' => $slug], ['name' => $name])->id;
                })->all();
            $listing->categories()->sync($categoryIds);
            foreach ($snapshots as $snapshot) {
                ContentSourceLink::create([
                    'source_snapshot_id' => $snapshot->id,
                    'sourceable_type' => Listing::class,
                    'sourceable_id' => $listing->id,
                    'role' => 'supporting',
                ]);
            }

            return $listing;
        });

        return [
            'duplicate' => false,
            'listing_id' => $listing->id,
            'slug' => $listing->slug,
            'status' => $listing->status,
            'unclaimed' => true,
            'source_snapshot_ids' => $snapshots->modelKeys(),
            'evidence' => $evidence,
            'requires_input' => ! $publish && (bool) ($arguments['publish'] ?? true),
            'question' => ! $publish && (bool) ($arguments['publish'] ?? true)
                ? 'The business identity or contact details need an official source or a second independent source before publication.'
                : null,
            'message' => $publish ? 'Unclaimed directory listing published.' : 'Listing saved as a draft because its essential facts need stronger evidence.',
        ];
    }

    private function duplicate(array $arguments): ?Listing
    {
        $host = $this->normalisedHost($arguments['website_url'] ?? null);
        $title = Str::lower(Str::squish($arguments['title']));
        $city = Str::lower(Str::squish((string) ($arguments['city'] ?? '')));

        return Listing::query()->get()->first(function (Listing $listing) use ($host, $title, $city, $arguments): bool {
            if ($host && $this->normalisedHost($listing->website_url) === $host) {
                return true;
            }
            if (! empty($arguments['email']) && Str::lower((string) $listing->email) === Str::lower($arguments['email'])) {
                return true;
            }

            return Str::lower(Str::squish($listing->title)) === $title
                && Str::lower(Str::squish((string) $listing->city)) === $city;
        });
    }

    private function evidenceAssessment($snapshots, ?string $website): array
    {
        $hosts = $snapshots->map(fn (SourceSnapshot $snapshot) => $this->normalisedHost($snapshot->url))->filter()->unique()->values();
        $officialHost = $this->normalisedHost($website);
        $official = $officialHost !== null && $hosts->contains($officialHost);

        return [
            'sufficient' => $official || $hosts->count() >= 2,
            'official_source' => $official,
            'independent_host_count' => $hosts->count(),
        ];
    }

    private function normalisedHost(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host !== '' ? preg_replace('/^www\./', '', $host) : null;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'business', 230, '');
        $slug = $base;
        for ($suffix = 2; Listing::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = Str::limit($base, 245, '').'-'.$suffix;
        }

        return $slug;
    }
}
