<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\Category;
use App\Models\ContentSourceLink;
use App\Models\Listing;
use App\Models\SourceSnapshot;
use App\Models\User;
use Illuminate\Support\Str;

class UpdateDirectoryListingTool implements OperatorTool
{
    public function name(): string
    {
        return 'directory.update_listing';
    }

    public function risk(): string
    {
        return 'R1';
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'description' => ['sometimes', 'string', 'max:20000'],
            'website_url' => ['nullable', 'url:https', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:draft,published'],
            'category_names' => ['sometimes', 'array', 'max:10'],
            'category_names.*' => ['string', 'max:100'],
            'source_snapshot_ids' => ['sometimes', 'array', 'max:10'],
            'source_snapshot_ids.*' => ['integer', 'distinct', 'exists:source_snapshots,id'],
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return hash('sha256', json_encode(Listing::findOrFail($arguments['listing_id'])->getAttributes(), JSON_UNESCAPED_SLASHES));
    }

    public function execute(User $user, array $arguments): array
    {
        $listing = Listing::findOrFail($arguments['listing_id']);
        $fields = collect($arguments)->only(['title', 'excerpt', 'description', 'website_url', 'email', 'phone', 'address_line', 'city', 'region', 'country', 'postal_code'])->all();
        $snapshots = SourceSnapshot::query()->whereIn('id', $arguments['source_snapshot_ids'] ?? [])->get();
        foreach ($snapshots as $snapshot) {
            ContentSourceLink::query()->firstOrCreate(['source_snapshot_id' => $snapshot->id, 'sourceable_type' => Listing::class, 'sourceable_id' => $listing->id], ['role' => 'supporting']);
        }
        if (isset($arguments['status'])) {
            $fields['status'] = $arguments['status'];
            $fields['published_at'] = $arguments['status'] === 'published' ? ($listing->published_at ?: now()) : null;
        }
        $listing->update($fields);
        if (isset($arguments['category_names'])) {
            $ids = collect($arguments['category_names'])->map(fn (string $name): int => Category::query()->firstOrCreate(['type' => 'listing', 'slug' => Str::slug($name)], ['name' => $name])->id)->all();
            $listing->categories()->sync($ids);
        }

        return ['listing_id' => $listing->id, 'slug' => $listing->slug, 'status' => $listing->fresh()->status, 'source_snapshot_ids' => $snapshots->modelKeys(), 'ownership_changed' => false];
    }
}
