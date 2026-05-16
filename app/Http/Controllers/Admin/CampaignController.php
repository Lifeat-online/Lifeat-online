<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\PushCampaign;
use App\Services\PushCampaignDispatchService;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    // ── Ad Campaigns ─────────────────────────────────────────────────────────

    public function adIndex(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim((string) $request->string('q'));

        $campaigns = AdCampaign::with(['listing', 'owner', 'activeSubscription.package'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhereHas('listing', fn ($l) => $l->where('title', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($campaigns);
        }

        return view('admin.campaigns.ads.index', [
            'campaigns' => $campaigns,
            'filters' => ['status' => $status, 'q' => $search],
            'statusOptions' => ['draft', 'ready', 'active', 'paused'],
        ]);
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
        $search = trim((string) $request->string('q'));

        $campaigns = PushCampaign::with(['listing', 'owner', 'activeSubscription.package'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($sent === 'yes', fn ($q) => $q->whereNotNull('sent_at'))
            ->when($sent === 'no', fn ($q) => $q->whereNull('sent_at'))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhereHas('listing', fn ($l) => $l->where('title', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($campaigns);
        }

        return view('admin.campaigns.push.index', [
            'campaigns' => $campaigns,
            'filters' => ['status' => $status, 'sent' => $sent, 'q' => $search],
            'statusOptions' => ['draft', 'ready', 'scheduled', 'active'],
        ]);
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
}
