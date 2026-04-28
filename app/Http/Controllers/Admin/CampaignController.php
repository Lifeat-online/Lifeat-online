<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AuditLog;
use App\Models\PushCampaign;
use App\Services\PushCampaignDispatchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    // ── Ad Campaigns ─────────────────────────────────────────────────────────

    public function adIndex(Request $request): View
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

        return view('admin.campaigns.ads.index', [
            'campaigns' => $campaigns,
            'filters' => ['status' => $status, 'q' => $search],
            'statusOptions' => ['draft', 'ready', 'active', 'paused'],
        ]);
    }

    public function adShow(AdCampaign $adCampaign): View
    {
        $adCampaign->load([
            'listing', 'owner', 'event', 'activeSubscription.package',
            'orderItems.order.invoices', 'orderItems.order.payments',
        ]);

        return view('admin.campaigns.ads.show', [
            'campaign' => $adCampaign,
        ]);
    }

    public function adApprove(Request $request, AdCampaign $adCampaign): RedirectResponse
    {
        abort_unless(in_array($adCampaign->status, ['ready', 'paused'], true), 422, 'Campaign is not in a state that can be approved.');

        $before = ['status' => $adCampaign->status];
        $adCampaign->update([
            'status' => 'active',
            'published_at' => $adCampaign->published_at ?? now(),
        ]);

        $this->logAudit($request, 'ad_campaign.approved', $adCampaign, $before, ['status' => 'active']);

        return redirect()->route('admin.campaigns.ads.show', $adCampaign)
            ->with('status', 'Campaign approved and set to active.');
    }

    public function adPause(Request $request, AdCampaign $adCampaign): RedirectResponse
    {
        abort_unless($adCampaign->status === 'active', 422, 'Only active campaigns can be paused.');

        $before = ['status' => $adCampaign->status];
        $adCampaign->update(['status' => 'paused']);
        $this->logAudit($request, 'ad_campaign.paused', $adCampaign, $before, ['status' => 'paused']);

        return redirect()->route('admin.campaigns.ads.show', $adCampaign)
            ->with('status', 'Campaign paused.');
    }

    public function adResume(Request $request, AdCampaign $adCampaign): RedirectResponse
    {
        abort_unless($adCampaign->status === 'paused', 422, 'Only paused campaigns can be resumed.');

        $before = ['status' => $adCampaign->status];
        $adCampaign->update(['status' => 'active']);
        $this->logAudit($request, 'ad_campaign.resumed', $adCampaign, $before, ['status' => 'active']);

        return redirect()->route('admin.campaigns.ads.show', $adCampaign)
            ->with('status', 'Campaign resumed.');
    }

    // ── Push Campaigns ────────────────────────────────────────────────────────

    public function pushIndex(Request $request): View
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

        return view('admin.campaigns.push.index', [
            'campaigns' => $campaigns,
            'filters' => ['status' => $status, 'sent' => $sent, 'q' => $search],
            'statusOptions' => ['draft', 'ready', 'scheduled', 'active'],
        ]);
    }

    public function pushShow(PushCampaign $pushCampaign): View
    {
        $pushCampaign->load(['listing', 'owner', 'event', 'activeSubscription.package', 'notificationLogs']);

        return view('admin.campaigns.push.show', [
            'campaign' => $pushCampaign,
            'deliveryLogs' => $pushCampaign->notificationLogs
                ->where('channel', 'push')
                ->sortByDesc('sent_at')
                ->values(),
        ]);
    }

    public function pushDispatch(Request $request, PushCampaign $pushCampaign, PushCampaignDispatchService $dispatchService): RedirectResponse
    {
        try {
            $notification = $dispatchService->dispatch($pushCampaign);
        } catch (\RuntimeException $exception) {
            return redirect()->route('admin.campaigns.push.show', $pushCampaign)
                ->withErrors(['campaign' => $exception->getMessage()]);
        }

        $this->logAudit($request, 'push_campaign.admin_dispatched', $pushCampaign,
            ['sent_at' => null],
            ['sent_at' => $pushCampaign->fresh()->sent_at?->toIso8601String(), 'notification_log_id' => $notification->id]
        );

        return redirect()->route('admin.campaigns.push.show', $pushCampaign)
            ->with('status', 'Push campaign dispatched by admin.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function logAudit(Request $request, string $action, $subject, array $before, array $after): void
    {
        AuditLog::create([
            'actor_user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
