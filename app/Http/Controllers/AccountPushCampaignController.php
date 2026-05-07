<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Listing;
use App\Models\PushCampaign;
use App\Models\AuditLog;
use App\Services\PushCampaignDispatchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountPushCampaignController extends Controller
{
    public function index(Request $request, Listing $listing): View
    {
        abort_unless($this->canAccessListing($request, $listing), 403);

        return view('account.push-campaigns.index', [
            'listing' => $listing->load('activeSubscription.package'),
            'campaigns' => PushCampaign::with([
                'event',
                'activeSubscription.package',
                'notificationLogs' => fn ($query) => $query->where('channel', 'push')->latest(),
                'orderItems.order.invoices',
                'orderItems.order.payments',
                'orderItems.package',
            ])
                ->where('listing_id', $listing->id)
                ->latest()
                ->paginate(12),
        ]);
    }

    public function create(Request $request, Listing $listing): View
    {
        abort_unless($this->canAccessListing($request, $listing), 403);

        return view('account.push-campaigns.form', [
            'listing' => $listing,
            'campaign' => new PushCampaign([
                'listing_id' => $listing->id,
                'target_city' => $listing->city,
                'target_region' => $listing->region,
                'audience_scope' => 'listing_city',
            ]),
            'events' => $listing->events()->orderByDesc('start_at')->get(),
            'pageTitle' => 'Create Push Campaign',
            'formAction' => route('account.listings.push-campaigns.store', $listing),
            'formMethod' => 'POST',
            'latestOrder' => null,
            'latestInvoice' => null,
            'latestPayment' => null,
            'dispatchLogs' => collect(),
        ]);
    }

    public function store(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless($this->canAccessListing($request, $listing), 403);
        $this->ensureEntitledListing($listing);

        $data = $this->validated($request, $listing);
        $this->ensureEventBelongsToListing($data['event_id'] ?? null, $listing);
        if (($data['status'] ?? null) === 'active') {
            $data['status'] = 'ready';
        }
        $data['listing_id'] = $listing->id;
        $data['user_id'] = $listing->user_id ?: $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['title']);

        $campaign = PushCampaign::create($data);

        return redirect()
            ->route('account.listings.push-campaigns.edit', [$listing, $campaign])
            ->with('status', 'Push campaign saved.');
    }

    public function edit(Request $request, Listing $listing, PushCampaign $pushCampaign): View
    {
        abort_unless($this->canAccessListing($request, $listing), 403);
        abort_unless($pushCampaign->listing_id === $listing->id, 404);

        $pushCampaign->load([
            'event',
            'activeSubscription.package',
            'notificationLogs' => fn ($query) => $query->where('channel', 'push')->latest(),
            'orderItems.order.invoices',
            'orderItems.order.payments',
            'orderItems.package',
        ]);

        $latestOrderItem = $pushCampaign->orderItems->sortByDesc('id')->first();
        $latestOrder = $latestOrderItem?->order;
        $latestInvoice = $latestOrder?->latestInvoice();
        $latestPayment = $latestOrder?->latestPayment();

        return view('account.push-campaigns.form', [
            'listing' => $listing,
            'campaign' => $pushCampaign,
            'events' => $listing->events()->orderByDesc('start_at')->get(),
            'pageTitle' => 'Edit Push Campaign',
            'formAction' => route('account.listings.push-campaigns.update', [$listing, $pushCampaign]),
            'formMethod' => 'PUT',
            'latestOrder' => $latestOrder,
            'latestInvoice' => $latestInvoice,
            'latestPayment' => $latestPayment,
            'dispatchLogs' => $pushCampaign->notificationLogs
                ->where('channel', 'push')
                ->sortByDesc('sent_at')
                ->take(5)
                ->values(),
        ]);
    }

    public function update(Request $request, Listing $listing, PushCampaign $pushCampaign): RedirectResponse
    {
        abort_unless($this->canAccessListing($request, $listing), 403);
        abort_unless($pushCampaign->listing_id === $listing->id, 404);

        $data = $this->validated($request, $listing);
        $this->ensureEventBelongsToListing($data['event_id'] ?? null, $listing);

        if (($data['status'] ?? null) === 'active') {
            $data['status'] = $pushCampaign->hasActivePushEntitlement() ? 'active' : 'ready';
        }

        $pushCampaign->update($data);

        return redirect()
            ->route('account.listings.push-campaigns.edit', [$listing, $pushCampaign])
            ->with('status', 'Push campaign updated.');
    }

    public function dispatch(Request $request, Listing $listing, PushCampaign $pushCampaign, PushCampaignDispatchService $dispatchService): RedirectResponse
    {
        abort_unless($this->canAccessListing($request, $listing), 403);
        abort_unless($pushCampaign->listing_id === $listing->id, 404);

        try {
            $notification = $dispatchService->dispatch($pushCampaign);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('account.listings.push-campaigns.edit', [$listing, $pushCampaign])
                ->withErrors(['campaign' => $exception->getMessage()]);
        }

        AuditLog::create([
            'actor_user_id' => $request->user()->id,
            'action' => 'push_campaign.dispatched',
            'subject_type' => PushCampaign::class,
            'subject_id' => $pushCampaign->id,
            'before_json' => ['sent_at' => $pushCampaign->getOriginal('sent_at')],
            'after_json' => [
                'sent_at' => $pushCampaign->fresh()->sent_at?->toIso8601String(),
                'notification_log_id' => $notification->id,
            ],
        ]);

        return redirect()
            ->route('account.listings.push-campaigns.edit', [$listing, $pushCampaign])
            ->with('status', 'Push campaign dispatched.');
    }

    public function destroy(Request $request, Listing $listing, PushCampaign $pushCampaign): RedirectResponse
    {
        abort_unless($this->canAccessListing($request, $listing), 403);
        abort_unless($pushCampaign->listing_id === $listing->id, 404);

        $pushCampaign->delete();

        return redirect()
            ->route('account.listings.push-campaigns.index', $listing)
            ->with('status', 'Push campaign removed.');
    }

    private function validated(Request $request, Listing $listing): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'schedule_at' => ['nullable', 'date'],
            'audience_scope' => ['required', 'in:listing_city,listing_region,custom_radius'],
            'target_city' => ['nullable', 'string', 'max:255'],
            'target_region' => ['nullable', 'string', 'max:255'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:200'],
            'status' => ['required', 'in:draft,ready,scheduled,active'],
        ]);
    }

    private function ensureEntitledListing(Listing $listing): void
    {
        if (! $listing->hasActiveBusinessEntitlement()) {
            throw ValidationException::withMessages([
                'listing' => 'Push campaigns require the linked business listing to have an active package.',
            ]);
        }
    }

    private function ensureEventBelongsToListing(?int $eventId, Listing $listing): void
    {
        if (! $eventId) {
            return;
        }

        if (! $listing->events()->whereKey($eventId)->exists()) {
            throw ValidationException::withMessages([
                'event_id' => 'Selected event must belong to this listing.',
            ]);
        }
    }

    private function uniqueSlug(string $title, ?PushCampaign $campaign = null): string
    {
        $base = Str::slug($title);
        $slug = $base !== '' ? $base : 'push-campaign';
        $suffix = 1;

        while (
            PushCampaign::query()
                ->where('slug', $slug)
                ->when($campaign, fn ($query) => $query->whereKeyNot($campaign->id))
                ->exists()
        ) {
            $slug = ($base !== '' ? $base : 'push-campaign').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function canAccessListing(Request $request, Listing $listing): bool
    {
        $user = $request->user();

        return $listing->user_id === $user->id
            || ($user->hasRole('staff') && $listing->registered_by_user_id === $user->id);
    }
}
