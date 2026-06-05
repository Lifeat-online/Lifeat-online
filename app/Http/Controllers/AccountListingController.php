<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\RespondToReviewRequest;
use App\Http\Requests\Account\StoreListingPhotoRequest;
use App\Http\Requests\Account\UpdateListingRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\Review;
use App\Support\Onboarding\ListingOnboardingChecklist;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AccountListingController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $user = $request->user();

        return view('account.listings.index', [
            'listings' => Listing::with(['activeSubscription.package', 'subscriptions.package'])
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);

                    if ($user->hasRole('staff')) {
                        $query->orWhere('registered_by_user_id', $user->id);
                    }
                })
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['status' => $status],
        ]);
    }

    public function show(Request $request, Listing $listing, ListingOnboardingChecklist $onboarding): View
    {
        Gate::authorize('manage', $listing);

        $listing->load([
            'categories',
            'adCampaigns',
            'events',
            'pushCampaigns',
            'photos',
            'activeSubscription.package',
            'subscriptions.package',
            'reviews.author',
            'reviews.responder',
            'orderItems.order.invoices',
            'orderItems.order.payments',
            'orderItems.package',
        ]);

        $latestOrderItem = $listing->orderItems->sortByDesc('id')->first();
        $latestOrder = $latestOrderItem?->order;
        $latestInvoice = $latestOrder?->latestInvoice();
        $latestPayment = $latestOrder?->latestPayment();

        return view('account.listings.show', [
            'listing' => $listing,
            'latestOrderItem' => $latestOrderItem,
            'latestOrder' => $latestOrder,
            'latestInvoice' => $latestInvoice,
            'latestPayment' => $latestPayment,
            'onboardingChecklist' => $onboarding->forListing($listing, $latestOrder),
        ]);
    }

    public function edit(Request $request, Listing $listing): View
    {
        Gate::authorize('manage', $listing);
        $listing->load('categories');

        return view('account.listings.form', [
            'listing' => $listing,
            'categories' => Category::where('type', 'listing')->orderBy('name')->get(),
            'selectedCategoryIds' => $listing->categories->modelKeys(),
        ]);
    }

    public function update(UpdateListingRequest $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('manage', $listing);

        $data = $request->validated();
        $data = $this->handleUploads($request, $data, $listing);

        $listing->update(Arr::except($data, [
            'category_ids',
            'featured_image_upload',
            'logo_upload',
            'remove_featured_image',
            'remove_logo',
        ]));
        $listing->categories()->sync($data['category_ids'] ?? []);

        return redirect()
            ->route('account.listings.edit', $listing)
            ->with('status', 'Listing profile updated.');
    }

    public function respondToReview(RespondToReviewRequest $request, Listing $listing, Review $review): RedirectResponse
    {
        Gate::authorize('manage', $listing);
        abort_unless($review->listing_id === $listing->id, 404);
        abort_unless($review->status === 'approved', 403);

        $data = $request->validated();

        $review->update([
            'owner_response' => $data['owner_response'],
            'owner_responded_at' => now(),
            'responded_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('account.listings.show', $listing)
            ->with('status', 'Review response saved.');
    }

    public function storePhoto(StoreListingPhotoRequest $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('manage', $listing);

        $data = $request->validated();

        $listing->photos()->create([
            'image_path' => $this->storeImage($request->file('photo_upload'), 'listings/gallery'),
            'caption' => $data['caption'] ?? null,
            'sort_order' => (($listing->photos()->max('sort_order') ?? -1) + 1),
        ]);

        return redirect()
            ->route('account.listings.show', $listing)
            ->with('status', 'Listing photo uploaded.');
    }

    public function destroyPhoto(Request $request, Listing $listing, ListingPhoto $photo): RedirectResponse
    {
        Gate::authorize('manage', $listing);
        abort_unless($photo->listing_id === $listing->id, 404);

        $this->deleteFile($photo->image_path);
        $photo->delete();

        return redirect()
            ->route('account.listings.show', $listing)
            ->with('status', 'Listing photo removed.');
    }

    public function makePrimaryPhoto(Request $request, Listing $listing, ListingPhoto $photo): RedirectResponse
    {
        Gate::authorize('manage', $listing);
        abort_unless($photo->listing_id === $listing->id, 404);

        $listing->photos()->increment('sort_order');
        $photo->update(['sort_order' => 0]);

        return redirect()
            ->route('account.listings.show', $listing)
            ->with('status', 'Primary listing photo updated.');
    }

    private function handleUploads(Request $request, array $data, Listing $listing): array
    {
        if ($request->boolean('remove_featured_image') && $listing->featured_image) {
            $this->deleteFile($listing->featured_image);
            $data['featured_image'] = null;
        } elseif ($request->hasFile('featured_image_upload')) {
            $this->deleteFile($listing->featured_image);
            $data['featured_image'] = $this->storeImage($request->file('featured_image_upload'), 'listings/featured');
        }

        if ($request->boolean('remove_logo') && $listing->logo_path) {
            $this->deleteFile($listing->logo_path);
            $data['logo_path'] = null;
        } elseif ($request->hasFile('logo_upload')) {
            $this->deleteFile($listing->logo_path);
            $data['logo_path'] = $this->storeImage($request->file('logo_upload'), 'listings/logos');
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

    public function destroy(Request $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('manage', $listing);

        $this->deleteListingFiles($listing);
        $listing->delete();

        return redirect()
            ->route('account.listings.index')
            ->with('status', 'Listing removed.');
    }

    private function deleteListingFiles(Listing $listing): void
    {
        $listing->loadMissing('photos');

        $this->deleteFile($listing->featured_image);
        $this->deleteFile($listing->logo_path);

        foreach ($listing->photos as $photo) {
            $this->deleteFile($photo->image_path);
        }
    }

}
