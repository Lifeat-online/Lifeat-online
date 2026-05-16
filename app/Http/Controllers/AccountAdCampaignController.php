<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\Event;
use App\Models\Listing;
use App\Support\Validation\UploadRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountAdCampaignController extends Controller
{
    public function index(Request $request, Listing $listing): View
    {
        Gate::authorize('manage', $listing);

        return view('account.ad-campaigns.index', [
            'listing' => $listing->load('activeSubscription.package'),
            'campaigns' => AdCampaign::with([
                'event',
                'activeSubscription.package',
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
        Gate::authorize('manage', $listing);

        return view('account.ad-campaigns.form', [
            'listing' => $listing,
            'campaign' => new AdCampaign([
                'listing_id' => $listing->id,
                'destination_url' => $listing->website_url,
            ]),
            'events' => $listing->events()->orderByDesc('start_at')->get(),
            'pageTitle' => 'Create Advert Campaign',
            'formAction' => route('account.listings.ad-campaigns.store', $listing),
            'formMethod' => 'POST',
            'latestOrder' => null,
            'latestInvoice' => null,
            'latestPayment' => null,
        ]);
    }

    public function store(Request $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('manage', $listing);
        $this->ensureEntitledListing($listing);

        $data = $this->validated($request, $listing);
        $data['listing_id'] = $listing->id;
        $data['user_id'] = $listing->user_id ?: $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data = $this->handleUploads($request, $data);

        $campaign = AdCampaign::create($data);

        return redirect()
            ->route('account.listings.ad-campaigns.edit', [$listing, $campaign])
            ->with('status', 'Advert campaign saved.');
    }

    public function edit(Request $request, Listing $listing, AdCampaign $adCampaign): View
    {
        Gate::authorize('manage', $listing);
        abort_unless($adCampaign->listing_id === $listing->id, 404);

        $adCampaign->load([
            'event',
            'activeSubscription.package',
            'orderItems.order.invoices',
            'orderItems.order.payments',
            'orderItems.package',
        ]);

        $latestOrderItem = $adCampaign->orderItems->sortByDesc('id')->first();
        $latestOrder = $latestOrderItem?->order;
        $latestInvoice = $latestOrder?->latestInvoice();
        $latestPayment = $latestOrder?->latestPayment();

        return view('account.ad-campaigns.form', [
            'listing' => $listing,
            'campaign' => $adCampaign,
            'events' => $listing->events()->orderByDesc('start_at')->get(),
            'pageTitle' => 'Edit Advert Campaign',
            'formAction' => route('account.listings.ad-campaigns.update', [$listing, $adCampaign]),
            'formMethod' => 'PUT',
            'latestOrder' => $latestOrder,
            'latestInvoice' => $latestInvoice,
            'latestPayment' => $latestPayment,
        ]);
    }

    public function update(Request $request, Listing $listing, AdCampaign $adCampaign): RedirectResponse
    {
        Gate::authorize('manage', $listing);
        abort_unless($adCampaign->listing_id === $listing->id, 404);

        $data = $this->validated($request, $listing);
        $data = $this->handleUploads($request, $data, $adCampaign);

        $adCampaign->update($data);

        return redirect()
            ->route('account.listings.ad-campaigns.edit', [$listing, $adCampaign])
            ->with('status', 'Advert campaign updated.');
    }

    public function destroy(Request $request, Listing $listing, AdCampaign $adCampaign): RedirectResponse
    {
        Gate::authorize('manage', $listing);
        abort_unless($adCampaign->listing_id === $listing->id, 404);

        $this->deleteFile($adCampaign->creative_image);
        $adCampaign->delete();

        return redirect()
            ->route('account.listings.ad-campaigns.index', $listing)
            ->with('status', 'Advert campaign removed.');
    }

    private function validated(Request $request, Listing $listing): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'destination_url' => ['nullable', 'url'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'placement' => ['required', 'in:banner,sitewide_banner,in_article_intro,in_article_mid,in_article_end,popup'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'creative_image_upload' => UploadRules::optionalPublicImage(),
            'remove_creative_image' => ['nullable', 'boolean'],
            'status' => ['required', 'in:draft,ready,active'],
        ] + ($listing->exists ? [] : []));
    }

    private function handleUploads(Request $request, array $data, ?AdCampaign $campaign = null): array
    {
        if ($request->boolean('remove_creative_image') && $campaign?->creative_image) {
            $this->deleteFile($campaign->creative_image);
            $data['creative_image'] = null;
        } elseif ($request->hasFile('creative_image_upload')) {
            $this->deleteFile($campaign?->creative_image);
            $data['creative_image'] = $this->storeImage($request->file('creative_image_upload'), 'campaigns/creative');
        }

        if (($data['status'] ?? null) === 'active') {
            $data['status'] = $campaign?->hasActiveAdvertEntitlement() ? 'active' : 'ready';
        }

        return $data;
    }

    private function storeImage(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function ensureEntitledListing(Listing $listing): void
    {
        if (! $listing->hasActiveBusinessEntitlement()) {
            throw ValidationException::withMessages([
                'listing' => 'Advert campaigns require the linked business listing to have an active package.',
            ]);
        }
    }

    private function uniqueSlug(string $title, ?AdCampaign $campaign = null): string
    {
        $base = Str::slug($title);
        $slug = $base !== '' ? $base : 'advert-campaign';
        $suffix = 1;

        while (
            AdCampaign::query()
                ->where('slug', $slug)
                ->when($campaign, fn ($query) => $query->whereKeyNot($campaign->id))
                ->exists()
        ) {
            $slug = ($base !== '' ? $base : 'advert-campaign').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

}
