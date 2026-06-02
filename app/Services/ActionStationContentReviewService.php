<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\AiGeneration;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Ai\AiPromptCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ActionStationContentReviewService
{
    public const FEATURE_KEY = 'content_review';

    public function __construct(
        private readonly AiGatewayService $gateway,
        private readonly AiPromptCatalog $prompts,
    ) {
    }

    public function settings(): array
    {
        return [
            'auto_publish' => filter_var((string) Setting::getValue('action_station.auto_publish_content', '0'), FILTER_VALIDATE_BOOL),
            'approval_threshold' => max(1, min(100, (int) Setting::getValue('action_station.ai_approval_threshold', 82))),
            'batch_limit' => max(1, min(25, (int) Setting::getValue('action_station.ai_review_batch_limit', 8))),
        ];
    }

    public function updateSettings(User $user, array $settings): void
    {
        $this->setSetting($user, 'action_station.auto_publish_content', ! empty($settings['auto_publish']) ? '1' : '0', 'boolean');
        $this->setSetting($user, 'action_station.ai_approval_threshold', (string) max(1, min(100, (int) $settings['approval_threshold'])), 'number');
        $this->setSetting($user, 'action_station.ai_review_batch_limit', (string) max(1, min(25, (int) $settings['batch_limit'])), 'number');
    }

    public function reviewByReference(string $type, int $id, ?User $user = null): array
    {
        return $this->review($this->resolveSource($type, $id), $user);
    }

    public function review(Model $source, ?User $user = null): array
    {
        $prompt = $this->prompts->get(self::FEATURE_KEY);

        $result = $this->gateway->generateStructured(
            self::FEATURE_KEY,
            $prompt['version'],
            $prompt['system'],
            [
                'instructions' => [
                    'target' => 'public Life@ content publication review',
                    'schema' => $prompt['schema'],
                    'allowed_recommendations' => ['approve', 'reject', 'human_review'],
                    'rules' => [
                        'Approve only if the content is safe, useful, specific, and publishable as supplied.',
                        'Use human_review for uncertainty, missing proof, entitlement confusion, risky claims, or anything a responsible operator should inspect.',
                        'Reject spam, scams, abusive content, unsafe claims, adult or illegal services, and unsupported medical/legal/financial promises.',
                        'Do not approve writer payout or finance work. This review is only for public-facing content.',
                    ],
                ],
                'settings' => $this->settings(),
                'content' => $this->sourcePayload($source),
            ],
            $source,
            $user,
            'en',
        );

        if (! ($result['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $result['message'] ?? 'AI review failed.',
                'generation' => $result['generation'] ?? null,
                'source' => $source,
            ];
        }

        $payload = $this->normalisePayload((array) ($result['payload'] ?? []));
        $settings = $this->settings();
        $eligibleBlockers = $this->eligibilityBlockers($source);
        $approvedByAi = $this->isApproved($payload, $settings['approval_threshold']);
        $autoPublished = false;
        $publishMessage = null;

        if ($approvedByAi && $settings['auto_publish'] && $eligibleBlockers === []) {
            $autoPublished = $this->publishSource($source);
            $publishMessage = $autoPublished ? 'AI approved and auto-published.' : 'AI approved, but no publish action was available.';
        } elseif ($approvedByAi && $settings['auto_publish'] && $eligibleBlockers !== []) {
            $publishMessage = 'AI approved, but publishing is waiting on entitlement or linkage checks.';
        }

        $status = $approvedByAi ? AiGeneration::STATUS_ACCEPTED : AiGeneration::STATUS_REJECTED;

        if (($result['generation'] ?? null) instanceof AiGeneration) {
            $result['generation']->update([
                'status' => $status,
                'output_payload' => $payload + ['eligibility_blockers' => $eligibleBlockers],
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
            ]);
        }

        return [
            'ok' => true,
            'approved' => $approvedByAi,
            'auto_published' => $autoPublished,
            'message' => $publishMessage ?: ($approvedByAi ? 'AI review approved this content.' : 'AI review routed this content to human review.'),
            'payload' => $payload + ['eligibility_blockers' => $eligibleBlockers],
            'generation' => ($result['generation'] ?? null)?->fresh(),
            'source' => $source->fresh(),
        ];
    }

    public function reviewQueue(?User $user = null, ?int $limit = null): array
    {
        $limit ??= $this->settings()['batch_limit'];
        $results = [];

        foreach ($this->pendingSources($limit) as $source) {
            $results[] = $this->review($source, $user);
        }

        return [
            'count' => count($results),
            'approved' => collect($results)->where('approved', true)->count(),
            'auto_published' => collect($results)->where('auto_published', true)->count(),
            'human_review' => collect($results)->where('approved', false)->count(),
            'results' => $results,
        ];
    }

    public function pendingSources(int $limit = 20): Collection
    {
        return collect()
            ->concat(Listing::with(['categories', 'activeSubscription'])->where('status', 'draft')->latest()->limit($limit)->get())
            ->concat(Event::with(['listing.activeSubscription', 'activeSubscription', 'categories'])->where('status', 'draft')->latest()->limit($limit)->get())
            ->concat(Voucher::with(['listing'])->where('status', 'draft')->latest()->limit($limit)->get())
            ->concat(AdCampaign::with(['listing.activeSubscription', 'activeSubscription', 'event'])->where('status', 'ready')->latest()->limit($limit)->get())
            ->filter(fn (Model $source): bool => $this->needsFreshReview($source))
            ->sortByDesc(fn (Model $source) => $source->updated_at?->timestamp ?? $source->created_at?->timestamp ?? 0)
            ->take($limit)
            ->values();
    }

    public function latestReview(Model $source): ?AiGeneration
    {
        return AiGeneration::query()
            ->where('feature_key', self::FEATURE_KEY)
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->latest()
            ->first();
    }

    public function needsFreshReview(Model $source): bool
    {
        $review = $this->latestReview($source);

        if (! $review) {
            return true;
        }

        return $source->updated_at && $review->created_at && $review->created_at->lt($source->updated_at);
    }

    public function typeFor(Model|string $source): string
    {
        $class = is_string($source) ? $source : $source::class;

        return match ($class) {
            Listing::class => 'listing',
            Event::class => 'event',
            Voucher::class => 'voucher',
            AdCampaign::class => 'ad_campaign',
            default => throw new \InvalidArgumentException('Unsupported review source ['.$class.'].'),
        };
    }

    public function labelFor(Model $source): string
    {
        return match (true) {
            $source instanceof Listing => 'Listing',
            $source instanceof Event => 'Event',
            $source instanceof Voucher => 'Voucher',
            $source instanceof AdCampaign => 'Ad Campaign',
            default => Str::headline(class_basename($source)),
        };
    }

    public function titleFor(Model $source): string
    {
        return (string) ($source->title ?? $source->headline ?? 'Untitled');
    }

    public function detailUrl(Model $source): string
    {
        return match (true) {
            $source instanceof Listing => route('admin.listings.edit', $source),
            $source instanceof Event => route('admin.events.edit', $source),
            $source instanceof Voucher => route('admin.vouchers.edit', $source->id),
            $source instanceof AdCampaign => route('admin.campaigns.ads.show', $source),
            default => '#',
        };
    }

    public function publishState(Model $source): string
    {
        return match (true) {
            $source instanceof AdCampaign => $source->status === 'active' ? 'live' : 'approved_waiting',
            property_exists($source, 'status') || isset($source->status) => $source->status === 'published' ? 'published' : 'approved_waiting',
            default => 'approved_waiting',
        };
    }

    private function resolveSource(string $type, int $id): Model
    {
        return match ($type) {
            'listing' => Listing::with(['categories', 'activeSubscription'])->findOrFail($id),
            'event' => Event::with(['listing.activeSubscription', 'activeSubscription', 'categories'])->findOrFail($id),
            'voucher' => Voucher::with(['listing'])->findOrFail($id),
            'ad_campaign' => AdCampaign::with(['listing.activeSubscription', 'activeSubscription', 'event'])->findOrFail($id),
            default => abort(404),
        };
    }

    private function normalisePayload(array $payload): array
    {
        $recommendation = Str::lower((string) ($payload['recommendation'] ?? 'human_review'));

        if (! in_array($recommendation, ['approve', 'reject', 'human_review'], true)) {
            $recommendation = 'human_review';
        }

        return [
            'recommendation' => $recommendation,
            'quality_score' => max(0, min(100, (int) ($payload['quality_score'] ?? 0))),
            'safety_score' => max(0, min(100, (int) ($payload['safety_score'] ?? 0))),
            'confidence_score' => max(0, min(100, (int) ($payload['confidence_score'] ?? 0))),
            'reasons' => $this->stringList($payload['reasons'] ?? []),
            'blocking_flags' => $this->stringList($payload['blocking_flags'] ?? []),
            'suggested_fixes' => $this->stringList($payload['suggested_fixes'] ?? []),
            'public_summary' => Str::limit((string) ($payload['public_summary'] ?? ''), 500, ''),
        ];
    }

    private function isApproved(array $payload, int $threshold): bool
    {
        return $payload['recommendation'] === 'approve'
            && $payload['quality_score'] >= $threshold
            && $payload['safety_score'] >= $threshold
            && $payload['confidence_score'] >= 65
            && $payload['blocking_flags'] === [];
    }

    private function eligibilityBlockers(Model $source): array
    {
        if ($source instanceof Event) {
            return $source->linkedListingHasActiveEntitlement() ? [] : ['Linked listing does not have an active business package yet.'];
        }

        if ($source instanceof Voucher) {
            return $source->listing && $source->listing->status === 'published' ? [] : ['Linked listing must be published before this voucher can go live.'];
        }

        if ($source instanceof AdCampaign) {
            $blockers = [];

            if (! $source->linkedListingHasActiveEntitlement()) {
                $blockers[] = 'Linked listing does not have an active business package yet.';
            }

            if (! $source->hasActiveAdvertEntitlement()) {
                $blockers[] = 'Advert campaign does not have an active advert package yet.';
            }

            return $blockers;
        }

        return [];
    }

    private function publishSource(Model $source): bool
    {
        if ($source instanceof Listing || $source instanceof Event || $source instanceof Voucher) {
            $source->update([
                'status' => 'published',
                'published_at' => $source->published_at ?: now(),
            ]);

            return true;
        }

        if ($source instanceof AdCampaign) {
            $source->update([
                'status' => 'active',
                'published_at' => $source->published_at ?: now(),
            ]);

            return true;
        }

        return false;
    }

    private function sourcePayload(Model $source): array
    {
        if ($source instanceof Listing) {
            $source->loadMissing(['categories', 'activeSubscription']);

            return [
                'type' => 'listing',
                'id' => $source->id,
                'title' => $source->title,
                'excerpt' => $source->excerpt,
                'description' => $source->description,
                'city' => $source->city,
                'region' => $source->region,
                'phone' => $source->phone,
                'email' => $source->email,
                'website_url' => $source->website_url,
                'categories' => $source->categories->pluck('name')->values()->all(),
                'source_channel' => $source->source_channel,
                'has_active_entitlement' => $source->hasActiveBusinessEntitlement(),
            ];
        }

        if ($source instanceof Event) {
            $source->loadMissing(['listing', 'categories', 'activeSubscription']);

            return [
                'type' => 'event',
                'id' => $source->id,
                'title' => $source->title,
                'excerpt' => $source->excerpt,
                'description' => $source->description,
                'venue_name' => $source->venue_name,
                'city' => $source->city,
                'region' => $source->region,
                'start_at' => $source->start_at?->toDateTimeString(),
                'end_at' => $source->end_at?->toDateTimeString(),
                'listing_title' => $source->listing?->title,
                'categories' => $source->categories->pluck('name')->values()->all(),
                'linked_listing_has_entitlement' => $source->linkedListingHasActiveEntitlement(),
                'has_active_event_entitlement' => $source->hasActiveEventEntitlement(),
            ];
        }

        if ($source instanceof Voucher) {
            $source->loadMissing('listing');

            return [
                'type' => 'voucher',
                'id' => $source->id,
                'title' => $source->title,
                'description' => $source->description,
                'voucher_type' => $source->voucher_type,
                'value' => $source->formattedValue(),
                'usage_limit' => $source->usage_limit,
                'start_at' => $source->start_at?->toDateTimeString(),
                'end_at' => $source->end_at?->toDateTimeString(),
                'terms' => $source->terms,
                'listing_title' => $source->listing?->title,
                'listing_status' => $source->listing?->status,
            ];
        }

        if ($source instanceof AdCampaign) {
            $source->loadMissing(['listing', 'event', 'activeSubscription']);

            return [
                'type' => 'ad_campaign',
                'id' => $source->id,
                'title' => $source->title,
                'headline' => $source->headline,
                'body' => $source->body,
                'placement' => $source->placement,
                'destination_url' => $source->destination_url,
                'listing_title' => $source->listing?->title,
                'event_title' => $source->event?->title,
                'linked_listing_has_entitlement' => $source->linkedListingHasActiveEntitlement(),
                'has_active_advert_entitlement' => $source->hasActiveAdvertEntitlement(),
            ];
        }

        throw new \InvalidArgumentException('Unsupported review source ['.$source::class.'].');
    }

    private function stringList(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function setSetting(User $user, string $key, string $value, string $type): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => 'action_station',
                'updated_by_user_id' => $user->id,
            ],
        );
    }
}
