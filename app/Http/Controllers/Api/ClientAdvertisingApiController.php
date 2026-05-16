<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\Event;
use App\Models\Listing;
use App\Models\MarketingIntegration;
use App\Models\PushCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClientAdvertisingApiController extends Controller
{
    public function listings(Request $request)
    {
        $listings = Listing::query()
            ->with(['activeSubscription.package'])
            ->withCount(['adCampaigns', 'pushCampaigns'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'listings' => $listings->map(fn (Listing $listing) => [
                'id' => $listing->id,
                'title' => $listing->title,
                'slug' => $listing->slug,
                'status' => $listing->status,
                'has_active_business_entitlement' => $listing->hasActiveBusinessEntitlement(),
                'ad_campaigns_count' => $listing->ad_campaigns_count,
                'push_campaigns_count' => $listing->push_campaigns_count,
            ])->values(),
        ]);
    }

    public function summary(Request $request, Listing $listing)
    {
        if (! $request->user()->hasRole('admin')) {
            Gate::authorize('own', $listing);
        }

        $listing->load([
            'activeSubscription.package',
            'events' => fn ($q) => $q->latest('start_at')->limit(20),
            'adCampaigns' => fn ($q) => $q->latest()->limit(20),
            'pushCampaigns' => fn ($q) => $q->latest()->limit(20),
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
                'updated_at' => $listing->updated_at?->toIso8601String(),
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
                'schedule_at' => $campaign->schedule_at?->toIso8601String(),
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

    public function updateIntegration(Request $request, Listing $listing, string $type)
    {
        if (! $request->user()->hasRole('admin')) {
            Gate::authorize('own', $listing);
        }

        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['inactive', 'active'])],
            'settings' => ['nullable', 'array'],
        ]);

        $integration = MarketingIntegration::query()->updateOrCreate(
            ['listing_id' => $listing->id, 'type' => $type],
            [
                'provider' => $validated['provider'] ?? null,
                'status' => $validated['status'],
                'settings_json' => $validated['settings'] ?? null,
                'updated_by_user_id' => $request->user()->id,
                'created_by_user_id' => $request->user()->id,
            ]
        );

        return response()->json([
            'ok' => true,
            'integration' => [
                'id' => $integration->id,
                'type' => $integration->type,
                'provider' => $integration->provider,
                'status' => $integration->status,
                'settings' => $integration->settings_json,
                'updated_at' => $integration->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
