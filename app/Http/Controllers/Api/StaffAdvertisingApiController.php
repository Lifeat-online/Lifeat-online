<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Listing;
use App\Models\MarketingIntegration;
use App\Models\PushCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StaffAdvertisingApiController extends Controller
{
    public function businesses(Request $request)
    {
        $user = $request->user();

        $businesses = Listing::query()
            ->with(['owner', 'activeSubscription.package'])
            ->withCount(['adCampaigns', 'pushCampaigns'])
            ->when(! $user->hasRole('admin'), fn ($query) => $query->where('registered_by_user_id', $user->id))
            ->orderBy('title')
            ->get();

        return response()->json([
            'businesses' => $businesses->map(fn (Listing $listing) => [
                'id' => $listing->id,
                'title' => $listing->title,
                'status' => $listing->status,
                'owner' => $listing->owner ? [
                    'id' => $listing->owner->id,
                    'name' => $listing->owner->name,
                    'email' => $listing->owner->email,
                ] : null,
                'ad_campaigns_count' => $listing->ad_campaigns_count,
                'push_campaigns_count' => $listing->push_campaigns_count,
            ])->values(),
        ]);
    }

    public function summary(Request $request, Listing $listing)
    {
        Gate::authorize('manageAssigned', $listing);

        $listing->load([
            'owner',
            'activeSubscription.package',
            'events' => fn ($q) => $q->latest('start_at')->limit(50),
            'adCampaigns' => fn ($q) => $q->latest()->limit(50),
            'pushCampaigns' => fn ($q) => $q->latest()->limit(50),
            'marketingIntegrations' => fn ($q) => $q->orderBy('type'),
        ]);

        return response()->json([
            'listing' => [
                'id' => $listing->id,
                'title' => $listing->title,
                'status' => $listing->status,
                'slug' => $listing->slug,
                'city' => $listing->city,
                'region' => $listing->region,
                'source_channel' => $listing->source_channel,
                'has_active_business_entitlement' => $listing->hasActiveBusinessEntitlement(),
                'owner' => $listing->owner ? [
                    'id' => $listing->owner->id,
                    'name' => $listing->owner->name,
                    'email' => $listing->owner->email,
                ] : null,
            ],
            'events' => $listing->events->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'status' => $event->status,
                'start_at' => $event->start_at?->toIso8601String(),
                'has_active_event_entitlement' => $event->hasActiveEventEntitlement(),
                'updated_at' => $event->updated_at?->toIso8601String(),
            ])->values(),
            'ad_campaigns' => $listing->adCampaigns->map(fn (AdCampaign $campaign) => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'status' => $campaign->status,
                'placement' => $campaign->placement,
                'budget_amount' => $campaign->budget_amount,
                'budget_currency' => $campaign->budget_currency,
                'targeting' => $campaign->targeting_json,
                'popup_settings' => $campaign->popup_settings_json,
                'start_at' => $campaign->start_at?->toIso8601String(),
                'end_at' => $campaign->end_at?->toIso8601String(),
                'impressions' => $campaign->impressions,
                'clicks' => $campaign->clicks,
                'ctr' => $campaign->ctr(),
                'updated_at' => $campaign->updated_at?->toIso8601String(),
            ])->values(),
            'push_campaigns' => $listing->pushCampaigns->map(fn (PushCampaign $campaign) => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'status' => $campaign->status,
                'budget_amount' => $campaign->budget_amount,
                'budget_currency' => $campaign->budget_currency,
                'schedule_at' => $campaign->schedule_at?->toIso8601String(),
                'audience_scope' => $campaign->audience_scope,
                'target_city' => $campaign->target_city,
                'target_region' => $campaign->target_region,
                'radius_km' => $campaign->radius_km,
                'open_count' => $campaign->open_count,
                'open_rate' => $campaign->openRate(),
                'updated_at' => $campaign->updated_at?->toIso8601String(),
            ])->values(),
            'integrations' => $listing->marketingIntegrations->map(fn (MarketingIntegration $integration) => [
                'id' => $integration->id,
                'type' => $integration->type,
                'provider' => $integration->provider,
                'status' => $integration->status,
                'settings' => $integration->settings_json,
                'updated_at' => $integration->updated_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function updateAdCampaign(Request $request, AdCampaign $adCampaign)
    {
        abort_unless($adCampaign->listing, 404);
        Gate::authorize('manageAssigned', $adCampaign->listing);

        $validated = $request->validate([
            'expected_updated_at' => ['required', 'date'],
            'status' => ['required', Rule::in(['draft', 'ready', 'active', 'paused'])],
            'placement' => ['required', Rule::in(['banner', 'sitewide_banner', 'in_article_intro', 'in_article_mid', 'in_article_end', 'popup'])],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'budget_currency' => ['required', 'string', 'max:8'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'targeting' => ['nullable', 'array'],
            'popup_settings' => ['nullable', 'array'],
        ]);

        $expected = Carbon::parse($validated['expected_updated_at']);
        if ($adCampaign->updated_at && $adCampaign->updated_at->ne($expected)) {
            return response()->json(['ok' => false, 'message' => 'Conflict: campaign was updated by someone else.'], 409);
        }

        $before = $adCampaign->only(['status', 'placement', 'budget_amount', 'budget_currency', 'start_at', 'end_at', 'targeting_json', 'popup_settings_json']);

        $adCampaign->update([
            'status' => $validated['status'],
            'placement' => $validated['placement'],
            'budget_amount' => $validated['budget_amount'] ?? null,
            'budget_currency' => $validated['budget_currency'],
            'start_at' => $validated['start_at'] ?? null,
            'end_at' => $validated['end_at'] ?? null,
            'targeting_json' => $validated['targeting'] ?? null,
            'popup_settings_json' => $validated['popup_settings'] ?? null,
        ]);

        $after = $adCampaign->fresh()->only(['status', 'placement', 'budget_amount', 'budget_currency', 'start_at', 'end_at', 'targeting_json', 'popup_settings_json']);
        $this->audit($request, 'staff.ad_campaign.updated', $adCampaign, $before, $after);

        return response()->json(['ok' => true, 'campaign' => [
            'id' => $adCampaign->id,
            'updated_at' => $adCampaign->fresh()->updated_at?->toIso8601String(),
        ]]);
    }

    public function updatePushCampaign(Request $request, PushCampaign $pushCampaign)
    {
        abort_unless($pushCampaign->listing, 404);
        Gate::authorize('manageAssigned', $pushCampaign->listing);

        $validated = $request->validate([
            'expected_updated_at' => ['required', 'date'],
            'status' => ['required', Rule::in(['draft', 'ready', 'scheduled', 'active'])],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'budget_currency' => ['required', 'string', 'max:8'],
            'schedule_at' => ['nullable', 'date'],
            'audience_scope' => ['nullable', 'string', 'max:255'],
            'target_city' => ['nullable', 'string', 'max:255'],
            'target_region' => ['nullable', 'string', 'max:255'],
            'radius_km' => ['nullable', 'numeric', 'min:0'],
        ]);

        $expected = Carbon::parse($validated['expected_updated_at']);
        if ($pushCampaign->updated_at && $pushCampaign->updated_at->ne($expected)) {
            return response()->json(['ok' => false, 'message' => 'Conflict: campaign was updated by someone else.'], 409);
        }

        $before = $pushCampaign->only(['status', 'budget_amount', 'budget_currency', 'schedule_at', 'audience_scope', 'target_city', 'target_region', 'radius_km']);

        $pushCampaign->update([
            'status' => $validated['status'],
            'budget_amount' => $validated['budget_amount'] ?? null,
            'budget_currency' => $validated['budget_currency'],
            'schedule_at' => $validated['schedule_at'] ?? null,
            'audience_scope' => $validated['audience_scope'] ?? null,
            'target_city' => $validated['target_city'] ?? null,
            'target_region' => $validated['target_region'] ?? null,
            'radius_km' => $validated['radius_km'] ?? null,
        ]);

        $after = $pushCampaign->fresh()->only(['status', 'budget_amount', 'budget_currency', 'schedule_at', 'audience_scope', 'target_city', 'target_region', 'radius_km']);
        $this->audit($request, 'staff.push_campaign.updated', $pushCampaign, $before, $after);

        return response()->json(['ok' => true, 'campaign' => [
            'id' => $pushCampaign->id,
            'updated_at' => $pushCampaign->fresh()->updated_at?->toIso8601String(),
        ]]);
    }

    public function updateIntegration(Request $request, Listing $listing, string $type)
    {
        $user = $request->user();
        Gate::authorize('manageAssigned', $listing);

        $validated = $request->validate([
            'expected_updated_at' => ['nullable', 'date'],
            'provider' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['inactive', 'active'])],
            'settings' => ['nullable', 'array'],
        ]);

        $integration = MarketingIntegration::query()->where('listing_id', $listing->id)->where('type', $type)->first();
        if ($integration && ! empty($validated['expected_updated_at'])) {
            $expected = Carbon::parse($validated['expected_updated_at']);
            if ($integration->updated_at && $integration->updated_at->ne($expected)) {
                return response()->json(['ok' => false, 'message' => 'Conflict: integration was updated by someone else.'], 409);
            }
        }

        $before = $integration?->toArray();

        $integration = MarketingIntegration::query()->updateOrCreate(
            ['listing_id' => $listing->id, 'type' => $type],
            [
                'provider' => $validated['provider'] ?? null,
                'status' => $validated['status'],
                'settings_json' => $validated['settings'] ?? null,
                'updated_by_user_id' => $user->id,
                'created_by_user_id' => $integration?->created_by_user_id ?? $user->id,
            ]
        );

        $after = $integration->fresh()->toArray();
        $this->audit($request, 'staff.integration.updated', $integration, $before ?: [], $after);

        return response()->json(['ok' => true, 'integration' => [
            'id' => $integration->id,
            'updated_at' => $integration->fresh()->updated_at?->toIso8601String(),
        ]]);
    }

    private function audit(Request $request, string $action, object $subject, array $before, array $after): void
    {
        AuditLog::create([
            'actor_user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id ?? null,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
