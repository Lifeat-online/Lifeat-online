<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\CampaignTrackingEvent;
use App\Models\Event;
use App\Models\Listing;
use App\Models\NotificationLog;
use App\Models\PushCampaign;
use App\Services\PushCampaignDispatchService;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\Validation\UploadRules;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    public function report(Request $request): View
    {
        $window = $this->reportWindow($request);

        return view('admin.campaigns.report', [
            'filters' => [
                'from' => $window['from']->toDateString(),
                'to' => $window['to']->toDateString(),
            ],
            'summary' => $this->reportSummary($window['from'], $window['to']),
            'dailyRows' => $this->aggregateDailyRows($window['from'], $window['to']),
            'topAds' => $this->reportAdCampaigns($window['from'], $window['to'], 10),
            'topPushCampaigns' => $this->reportPushCampaigns($window['from'], $window['to'], 10),
        ]);
    }

    public function reportExport(Request $request, string $dataset): StreamedResponse
    {
        abort_unless(in_array($dataset, ['ad-summary', 'push-summary', 'tracking-events'], true), 404);

        $window = $this->reportWindow($request);

        return match ($dataset) {
            'ad-summary' => $this->streamAdSummaryExport($window['from'], $window['to']),
            'push-summary' => $this->streamPushSummaryExport($window['from'], $window['to']),
            default => $this->streamTrackingEventExport($window['from'], $window['to']),
        };
    }

    // ── Ad Campaigns ─────────────────────────────────────────────────────────

    public function adIndex(Request $request)
    {
        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();
        $search = trim((string) $request->string('q'));

        $campaignsQuery = AdCampaign::with(['listing', 'owner', 'activeSubscription.package'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhereHas('listing', fn ($l) => $l->where('title', 'like', "%{$search}%"));
                });
            });

        match ($sort) {
            'impressions' => $campaignsQuery->orderByDesc('impressions')->orderByDesc('created_at'),
            'clicks' => $campaignsQuery->orderByDesc('clicks')->orderByDesc('created_at'),
            'ctr' => $campaignsQuery->orderByRaw('CASE WHEN impressions > 0 THEN (clicks * 1.0 / impressions) ELSE 0 END DESC')->orderByDesc('created_at'),
            default => $campaignsQuery->latest(),
        };

        $campaigns = $campaignsQuery
            ->paginate(20)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($campaigns);
        }

        return view('admin.campaigns.ads.index', [
            'campaigns' => $campaigns,
            'filters' => ['status' => $status, 'q' => $search, 'sort' => $sort],
            'statusOptions' => ['draft', 'ready', 'active', 'paused'],
            'sortOptions' => [
                '' => 'Newest first',
                'impressions' => 'Most impressions',
                'clicks' => 'Most clicks',
                'ctr' => 'Best CTR',
            ],
        ]);
    }

    public function adCreate(): View
    {
        return view('admin.campaigns.ads.form', [
            'campaign' => new AdCampaign([
                'status' => 'draft',
                'placement' => 'banner',
                'budget_currency' => 'ZAR',
            ]),
            'listings' => $this->campaignListings(),
            'events' => $this->campaignEvents(),
            'pageTitle' => 'Add Ad Campaign',
            'formAction' => route('admin.campaigns.ads.store'),
        ]);
    }

    public function adStore(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validatedAdCampaign($request);
        $listing = Listing::findOrFail($data['listing_id']);
        $this->ensureEventBelongsToListing($data['event_id'] ?? null, $listing);

        $data['user_id'] = $listing->user_id ?: $request->user()->id;
        $data['slug'] = $this->uniqueAdSlug($data['title']);
        $data = $this->handleAdUploads($request, $data);

        if (($data['status'] ?? null) === 'active') {
            $data['status'] = 'ready';
        }

        $campaign = AdCampaign::create($data);
        $audit->log($request, 'ad_campaign.admin_created', $campaign, [], $campaign->only(['listing_id', 'title', 'status', 'placement']));

        return redirect()->route('admin.campaigns.ads.show', $campaign)
            ->with('status', 'Ad campaign created.');
    }

    public function adShow(AdCampaign $adCampaign)
    {
        $adCampaign->load([
            'listing', 'owner', 'event', 'activeSubscription.package',
            'orderItems.order.invoices', 'orderItems.order.payments',
        ]);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'campaign' => $adCampaign]);
        }

        return view('admin.campaigns.ads.show', [
            'campaign' => $adCampaign,
            'dailyRows' => $this->dailyTrackingRows($adCampaign, [
                CampaignTrackingEvent::TYPE_IMPRESSION,
                CampaignTrackingEvent::TYPE_CLICK,
            ]),
            'recentTrackingEvents' => $this->recentTrackingEvents($adCampaign),
        ]);
    }

    public function adApprove(Request $request, AdCampaign $adCampaign, AuditLogService $audit)
    {
        abort_unless(in_array($adCampaign->status, ['ready', 'paused'], true), 422, 'Campaign is not in a state that can be approved.');
        abort_unless($adCampaign->linkedListingHasActiveEntitlement(), 422, 'The linked business listing needs an active package before advert approval.');
        abort_unless($adCampaign->hasActiveAdvertEntitlement(), 422, 'This advert campaign needs an active advert package before approval.');

        $before = ['status' => $adCampaign->status];
        $adCampaign->update([
            'status' => 'active',
            'published_at' => $adCampaign->published_at ?? now(),
        ]);

        $audit->log($request, 'ad_campaign.approved', $adCampaign, $before, ['status' => 'active']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'campaign' => $adCampaign->fresh()]);
        }

        return redirect()->route('admin.campaigns.ads.show', $adCampaign)
            ->with('status', 'Campaign approved and set to active.');
    }

    public function adPause(Request $request, AdCampaign $adCampaign, AuditLogService $audit)
    {
        abort_unless($adCampaign->status === 'active', 422, 'Only active campaigns can be paused.');

        $before = ['status' => $adCampaign->status];
        $adCampaign->update(['status' => 'paused']);
        $audit->log($request, 'ad_campaign.paused', $adCampaign, $before, ['status' => 'paused']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'campaign' => $adCampaign->fresh()]);
        }

        return redirect()->route('admin.campaigns.ads.show', $adCampaign)
            ->with('status', 'Campaign paused.');
    }

    public function adResume(Request $request, AdCampaign $adCampaign, AuditLogService $audit)
    {
        abort_unless($adCampaign->status === 'paused', 422, 'Only paused campaigns can be resumed.');

        $before = ['status' => $adCampaign->status];
        $adCampaign->update(['status' => 'active']);
        $audit->log($request, 'ad_campaign.resumed', $adCampaign, $before, ['status' => 'active']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'campaign' => $adCampaign->fresh()]);
        }

        return redirect()->route('admin.campaigns.ads.show', $adCampaign)
            ->with('status', 'Campaign resumed.');
    }

    public function adBulk(Request $request, AuditLogService $audit)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'pause', 'resume'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $targets = AdCampaign::query()->whereIn('id', $validated['ids'])->get();

        foreach ($targets as $campaign) {
            $before = ['status' => $campaign->status];

            match ($validated['action']) {
                'approve' => (function () use ($campaign) {
                    abort_unless(in_array($campaign->status, ['ready', 'paused'], true), 422, 'Campaign is not in a state that can be approved.');
                    abort_unless($campaign->linkedListingHasActiveEntitlement(), 422, 'The linked business listing needs an active package before advert approval.');
                    abort_unless($campaign->hasActiveAdvertEntitlement(), 422, 'This advert campaign needs an active advert package before approval.');
                    $campaign->update(['status' => 'active', 'published_at' => $campaign->published_at ?? now()]);
                })(),
                'pause' => (function () use ($campaign) {
                    abort_unless($campaign->status === 'active', 422, 'Only active campaigns can be paused.');
                    $campaign->update(['status' => 'paused']);
                })(),
                'resume' => (function () use ($campaign) {
                    abort_unless($campaign->status === 'paused', 422, 'Only paused campaigns can be resumed.');
                    $campaign->update(['status' => 'active']);
                })(),
            };

            $audit->log($request, 'ad_campaign.bulk_'.$validated['action'], $campaign, $before, ['status' => $campaign->fresh()->status]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'affected' => $targets->count()]);
        }

        return redirect()->route('admin.campaigns.ads.index')->with('status', 'Bulk operation completed.');
    }

    // ── Push Campaigns ────────────────────────────────────────────────────────

    public function pushIndex(Request $request)
    {
        $status = $request->string('status')->toString();
        $sent = $request->string('sent')->toString();
        $sort = $request->string('sort')->toString();
        $search = trim((string) $request->string('q'));

        $campaignsQuery = PushCampaign::with(['listing', 'owner', 'activeSubscription.package'])
            ->withCount([
                'notificationLogs as push_delivery_count' => fn ($query) => $query->where('channel', 'push'),
            ])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($sent === 'yes', fn ($q) => $q->whereNotNull('sent_at'))
            ->when($sent === 'no', fn ($q) => $q->whereNull('sent_at'))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhereHas('listing', fn ($l) => $l->where('title', 'like', "%{$search}%"));
                });
            });

        match ($sort) {
            'opens' => $campaignsQuery->orderByDesc('open_count')->orderByDesc('created_at'),
            'sent' => $campaignsQuery->orderByDesc('sent_at')->orderByDesc('created_at'),
            default => $campaignsQuery->latest(),
        };

        $campaigns = $campaignsQuery
            ->paginate(20)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($campaigns);
        }

        return view('admin.campaigns.push.index', [
            'campaigns' => $campaigns,
            'filters' => ['status' => $status, 'sent' => $sent, 'q' => $search, 'sort' => $sort],
            'statusOptions' => ['draft', 'ready', 'scheduled', 'active'],
            'sortOptions' => [
                '' => 'Newest first',
                'opens' => 'Most opens',
                'sent' => 'Recently sent',
            ],
        ]);
    }

    public function pushCreate(): View
    {
        return view('admin.campaigns.push.form', [
            'campaign' => new PushCampaign([
                'status' => 'draft',
                'audience_scope' => 'listing_city',
                'budget_currency' => 'ZAR',
            ]),
            'listings' => $this->campaignListings(),
            'events' => $this->campaignEvents(),
            'pageTitle' => 'Add Push Campaign',
            'formAction' => route('admin.campaigns.push.store'),
        ]);
    }

    public function pushStore(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validatedPushCampaign($request);
        $listing = Listing::findOrFail($data['listing_id']);
        $this->ensureEventBelongsToListing($data['event_id'] ?? null, $listing);

        $data['user_id'] = $listing->user_id ?: $request->user()->id;
        $data['slug'] = $this->uniquePushSlug($data['title']);

        if (($data['status'] ?? null) === 'active') {
            $data['status'] = 'ready';
        }

        $campaign = PushCampaign::create($data);
        $audit->log($request, 'push_campaign.admin_created', $campaign, [], $campaign->only(['listing_id', 'title', 'status', 'audience_scope']));

        return redirect()->route('admin.campaigns.push.show', $campaign)
            ->with('status', 'Push campaign created.');
    }

    public function pushShow(PushCampaign $pushCampaign)
    {
        $pushCampaign->load(['listing', 'owner', 'event', 'activeSubscription.package', 'notificationLogs']);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'campaign' => $pushCampaign]);
        }

        return view('admin.campaigns.push.show', [
            'campaign' => $pushCampaign,
            'deliveryLogs' => $pushCampaign->notificationLogs
                ->where('channel', 'push')
                ->sortByDesc('sent_at')
                ->values(),
            'dailyRows' => $this->dailyTrackingRows($pushCampaign, [
                CampaignTrackingEvent::TYPE_PUSH_OPEN,
            ]),
            'recentTrackingEvents' => $this->recentTrackingEvents($pushCampaign),
        ]);
    }

    public function pushDispatch(Request $request, PushCampaign $pushCampaign, PushCampaignDispatchService $dispatchService, AuditLogService $audit)
    {
        try {
            $notification = $dispatchService->dispatch($pushCampaign);
        } catch (\RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }
            return redirect()->route('admin.campaigns.push.show', $pushCampaign)
                ->withErrors(['campaign' => $exception->getMessage()]);
        }

        $audit->log($request, 'push_campaign.admin_dispatched', $pushCampaign,
            ['sent_at' => null],
            ['sent_at' => $pushCampaign->fresh()->sent_at?->toIso8601String(), 'notification_log_id' => $notification->id]
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'campaign' => $pushCampaign->fresh(), 'notification_log_id' => $notification->id]);
        }

        return redirect()->route('admin.campaigns.push.show', $pushCampaign)
            ->with('status', 'Push campaign dispatched by admin.');
    }

    public function pushBulk(Request $request, PushCampaignDispatchService $dispatchService, AuditLogService $audit)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['dispatch'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $targets = PushCampaign::query()->whereIn('id', $validated['ids'])->get();

        foreach ($targets as $campaign) {
            if ($validated['action'] !== 'dispatch') {
                continue;
            }

            try {
                $notification = $dispatchService->dispatch($campaign);
            } catch (\RuntimeException $exception) {
                if ($request->expectsJson()) {
                    return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
                }
                return redirect()->route('admin.campaigns.push.index')
                    ->withErrors(['campaign' => $exception->getMessage()]);
            }

            $audit->log($request, 'push_campaign.bulk_dispatch', $campaign,
                ['sent_at' => null],
                ['sent_at' => $campaign->fresh()->sent_at?->toIso8601String(), 'notification_log_id' => $notification->id]
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'affected' => $targets->count()]);
        }

        return redirect()->route('admin.campaigns.push.index')->with('status', 'Bulk operation completed.');
    }

    private function campaignListings()
    {
        return Listing::with('owner')
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'user_id', 'city', 'region', 'website_url']);
    }

    private function campaignEvents()
    {
        return Event::query()
            ->with('listing:id,title')
            ->orderByDesc('start_at')
            ->get(['id', 'listing_id', 'title', 'start_at']);
    }

    private function validatedAdCampaign(Request $request): array
    {
        return $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'title' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'destination_url' => ['nullable', 'url'],
            'placement' => ['required', 'in:banner,sitewide_banner,in_article_intro,in_article_mid,in_article_end,popup'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'creative_image_upload' => UploadRules::optionalPublicImage(),
            'status' => ['required', Rule::in(['draft', 'ready', 'active', 'paused'])],
        ]);
    }

    private function validatedPushCampaign(Request $request): array
    {
        return $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'title' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'schedule_at' => ['nullable', 'date'],
            'audience_scope' => ['required', Rule::in(['listing_city', 'listing_region', 'custom_radius'])],
            'target_city' => ['nullable', 'string', 'max:255'],
            'target_region' => ['nullable', 'string', 'max:255'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:200'],
            'status' => ['required', Rule::in(['draft', 'ready', 'scheduled', 'active'])],
        ]);
    }

    private function ensureEventBelongsToListing(?int $eventId, Listing $listing): void
    {
        if (! $eventId) {
            return;
        }

        if (! $listing->events()->whereKey($eventId)->exists()) {
            throw ValidationException::withMessages([
                'event_id' => 'Selected event must belong to the selected listing.',
            ]);
        }
    }

    private function handleAdUploads(Request $request, array $data): array
    {
        if ($request->hasFile('creative_image_upload')) {
            $data['creative_image'] = $this->storeImage($request->file('creative_image_upload'), 'campaigns/creative');
        }

        return $data;
    }

    private function storeImage(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    private function uniqueAdSlug(string $title): string
    {
        return $this->uniqueCampaignSlug($title, AdCampaign::class, 'advert-campaign');
    }

    private function uniquePushSlug(string $title): string
    {
        return $this->uniqueCampaignSlug($title, PushCampaign::class, 'push-campaign');
    }

    private function uniqueCampaignSlug(string $title, string $modelClass, string $fallback): string
    {
        $base = Str::slug($title);
        $slug = $base !== '' ? $base : $fallback;
        $suffix = 1;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = ($base !== '' ? $base : $fallback).'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function reportWindow(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? now()->toDateString())->endOfDay();

        return compact('from', 'to');
    }

    private function reportSummary(Carbon $from, Carbon $to): array
    {
        $impressions = $this->trackingCount(CampaignTrackingEvent::TYPE_IMPRESSION, $from, $to);
        $clicks = $this->trackingCount(CampaignTrackingEvent::TYPE_CLICK, $from, $to);
        $opens = $this->trackingCount(CampaignTrackingEvent::TYPE_PUSH_OPEN, $from, $to);
        $deliveries = $this->pushDeliveryCount($from, $to);

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0,
            'push_deliveries' => $deliveries,
            'push_opens' => $opens,
            'push_open_rate' => $deliveries > 0 ? round(($opens / $deliveries) * 100, 2) : 0.0,
        ];
    }

    private function aggregateDailyRows(Carbon $from, Carbon $to): array
    {
        $trackingRows = CampaignTrackingEvent::query()
            ->selectRaw('DATE(occurred_at) as event_date, event_type, COUNT(*) as total')
            ->whereBetween('occurred_at', [$from, $to])
            ->groupBy('event_date', 'event_type')
            ->get()
            ->keyBy(fn ($event) => $event->event_date.'.'.$event->event_type);

        $deliveryRows = NotificationLog::query()
            ->selectRaw('DATE(sent_at) as event_date, COUNT(*) as total')
            ->where('channel', 'push')
            ->where('notifiable_type', PushCampaign::class)
            ->whereBetween('sent_at', [$from, $to])
            ->groupBy('event_date')
            ->get()
            ->keyBy('event_date');

        $rows = [];

        for ($date = $to->copy()->startOfDay(); $date->greaterThanOrEqualTo($from->copy()->startOfDay()); $date->subDay()) {
            $keyDate = $date->toDateString();
            $impressions = (int) ($trackingRows->get($keyDate.'.'.CampaignTrackingEvent::TYPE_IMPRESSION)?->total ?? 0);
            $clicks = (int) ($trackingRows->get($keyDate.'.'.CampaignTrackingEvent::TYPE_CLICK)?->total ?? 0);
            $pushDeliveries = (int) ($deliveryRows->get($keyDate)?->total ?? 0);
            $pushOpens = (int) ($trackingRows->get($keyDate.'.'.CampaignTrackingEvent::TYPE_PUSH_OPEN)?->total ?? 0);

            $rows[] = [
                'date' => $date->copy(),
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0,
                'push_deliveries' => $pushDeliveries,
                'push_opens' => $pushOpens,
                'push_open_rate' => $pushDeliveries > 0 ? round(($pushOpens / $pushDeliveries) * 100, 2) : 0.0,
            ];
        }

        return $rows;
    }

    private function trackingCount(string $eventType, Carbon $from, Carbon $to): int
    {
        return CampaignTrackingEvent::query()
            ->where('event_type', $eventType)
            ->whereBetween('occurred_at', [$from, $to])
            ->count();
    }

    private function pushDeliveryCount(Carbon $from, Carbon $to): int
    {
        return NotificationLog::query()
            ->where('channel', 'push')
            ->where('notifiable_type', PushCampaign::class)
            ->whereBetween('sent_at', [$from, $to])
            ->count();
    }

    private function reportAdCampaigns(Carbon $from, Carbon $to, ?int $limit = null)
    {
        $query = AdCampaign::with(['listing', 'owner'])
            ->withCount([
                'trackingEvents as report_impressions' => fn ($query) => $query
                    ->where('event_type', CampaignTrackingEvent::TYPE_IMPRESSION)
                    ->whereBetween('occurred_at', [$from, $to]),
                'trackingEvents as report_clicks' => fn ($query) => $query
                    ->where('event_type', CampaignTrackingEvent::TYPE_CLICK)
                    ->whereBetween('occurred_at', [$from, $to]),
            ])
            ->orderByDesc('report_clicks')
            ->orderByDesc('report_impressions')
            ->orderBy('title');

        return $limit ? $query->limit($limit)->get() : $query->get();
    }

    private function reportPushCampaigns(Carbon $from, Carbon $to, ?int $limit = null)
    {
        $query = PushCampaign::with(['listing', 'owner'])
            ->withCount([
                'notificationLogs as report_deliveries' => fn ($query) => $query
                    ->where('channel', 'push')
                    ->whereBetween('sent_at', [$from, $to]),
                'trackingEvents as report_opens' => fn ($query) => $query
                    ->where('event_type', CampaignTrackingEvent::TYPE_PUSH_OPEN)
                    ->whereBetween('occurred_at', [$from, $to]),
            ])
            ->orderByDesc('report_opens')
            ->orderByDesc('report_deliveries')
            ->orderBy('title');

        return $limit ? $query->limit($limit)->get() : $query->get();
    }

    private function streamAdSummaryExport(Carbon $from, Carbon $to): StreamedResponse
    {
        $campaigns = $this->reportAdCampaigns($from, $to);

        return response()->streamDownload(function () use ($campaigns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Campaign', 'Listing', 'Owner', 'Status', 'Impressions', 'Clicks', 'CTR']);
            foreach ($campaigns as $campaign) {
                $impressions = (int) $campaign->report_impressions;
                $clicks = (int) $campaign->report_clicks;
                fputcsv($handle, [
                    $campaign->title,
                    $campaign->listing?->title,
                    $campaign->owner?->email,
                    $campaign->status,
                    $impressions,
                    $clicks,
                    $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                ]);
            }
            fclose($handle);
        }, 'campaign-ad-summary-export.csv');
    }

    private function streamPushSummaryExport(Carbon $from, Carbon $to): StreamedResponse
    {
        $campaigns = $this->reportPushCampaigns($from, $to);

        return response()->streamDownload(function () use ($campaigns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Campaign', 'Listing', 'Owner', 'Status', 'Sent At', 'Deliveries', 'Opens', 'Open Rate']);
            foreach ($campaigns as $campaign) {
                $deliveries = (int) $campaign->report_deliveries;
                $opens = (int) $campaign->report_opens;
                fputcsv($handle, [
                    $campaign->title,
                    $campaign->listing?->title,
                    $campaign->owner?->email,
                    $campaign->sent_at ? 'sent' : $campaign->status,
                    optional($campaign->sent_at)->toDateTimeString(),
                    $deliveries,
                    $opens,
                    $deliveries > 0 ? round(($opens / $deliveries) * 100, 2) : 0,
                ]);
            }
            fclose($handle);
        }, 'campaign-push-summary-export.csv');
    }

    private function streamTrackingEventExport(Carbon $from, Carbon $to): StreamedResponse
    {
        $events = CampaignTrackingEvent::with('trackable')
            ->whereBetween('occurred_at', [$from, $to])
            ->latest('occurred_at')
            ->get();

        return response()->streamDownload(function () use ($events) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Occurred At', 'Campaign Type', 'Campaign', 'Event', 'Token', 'Referrer']);
            foreach ($events as $event) {
                fputcsv($handle, [
                    optional($event->occurred_at)->toDateTimeString(),
                    $this->campaignTypeLabel($event->trackable_type),
                    $event->trackable?->title,
                    $event->event_type,
                    $event->tracking_token,
                    $event->referrer,
                ]);
            }
            fclose($handle);
        }, 'campaign-tracking-events-export.csv');
    }

    private function campaignTypeLabel(string $trackableType): string
    {
        return match ($trackableType) {
            AdCampaign::class => 'Ad',
            PushCampaign::class => 'Push',
            default => class_basename($trackableType),
        };
    }

    private function dailyTrackingRows(AdCampaign|PushCampaign $campaign, array $eventTypes, int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $events = CampaignTrackingEvent::query()
            ->selectRaw('DATE(occurred_at) as event_date, event_type, COUNT(*) as total')
            ->where('trackable_type', $campaign->getMorphClass())
            ->where('trackable_id', $campaign->getKey())
            ->whereIn('event_type', $eventTypes)
            ->where('occurred_at', '>=', $start)
            ->groupBy('event_date', 'event_type')
            ->get()
            ->keyBy(fn ($event) => $event->event_date.'.'.$event->event_type);

        $rows = [];

        for ($date = now()->startOfDay(); $date->greaterThanOrEqualTo($start); $date->subDay()) {
            $keyDate = $date->toDateString();
            $row = ['date' => $date->copy()];

            foreach ($eventTypes as $eventType) {
                $row[$eventType] = (int) ($events->get($keyDate.'.'.$eventType)?->total ?? 0);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function recentTrackingEvents(AdCampaign|PushCampaign $campaign)
    {
        return CampaignTrackingEvent::query()
            ->where('trackable_type', $campaign->getMorphClass())
            ->where('trackable_id', $campaign->getKey())
            ->latest('occurred_at')
            ->limit(25)
            ->get();
    }
}
