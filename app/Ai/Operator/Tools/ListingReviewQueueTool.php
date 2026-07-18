<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\Listing;
use App\Models\User;

class ListingReviewQueueTool implements OperatorTool
{
    public function name(): string
    {
        return 'listings.review_queue';
    }

    public function risk(): string
    {
        return 'R0';
    }

    public function rules(): array
    {
        return ['limit' => ['sometimes', 'integer', 'min:1', 'max:50']];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support', 'dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return 'read-only';
    }

    public function execute(User $user, array $arguments): array
    {
        $listings = Listing::query()->whereIn('status', ['draft', 'pending_review'])->latest('updated_at')
            ->limit((int) ($arguments['limit'] ?? 10))->get(['id', 'title', 'status', 'updated_at']);

        return ['count' => $listings->count(), 'listings' => $listings->map(fn (Listing $listing): array => [
            'id' => $listing->id,
            'title' => $listing->title,
            'status' => $listing->status,
            'updated_at' => $listing->updated_at?->toIso8601String(),
            'url' => route('admin.listings.edit', $listing),
        ])->all()];
    }
}
