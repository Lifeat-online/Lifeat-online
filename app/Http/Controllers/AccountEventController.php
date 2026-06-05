<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\SaveEventRequest;
use App\Models\Category;
use App\Models\Event;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountEventController extends Controller
{
    public function index(Request $request, Listing $listing): View
    {
        Gate::authorize('manage', $listing);

        return view('account.events.index', [
            'listing' => $listing->load('activeSubscription.package'),
            'events' => Event::with([
                'categories',
                'activeSubscription.package',
                'orderItems.order.invoices',
                'orderItems.order.payments',
                'orderItems.package',
            ])
                ->where('listing_id', $listing->id)
                ->latest('start_at')
                ->paginate(12),
        ]);
    }

    public function create(Request $request, Listing $listing): View
    {
        Gate::authorize('manage', $listing);

        return view('account.events.form', [
            'listing' => $listing,
            'event' => new Event([
                'listing_id' => $listing->id,
                'city' => $listing->city,
                'region' => $listing->region,
                'country' => $listing->country,
            ]),
            'categories' => Category::where('type', 'event')->orderBy('name')->get(),
            'selectedCategoryIds' => [],
            'pageTitle' => 'Create Event',
            'formAction' => route('account.listings.events.store', $listing),
            'formMethod' => 'POST',
            'latestOrderItem' => null,
            'latestOrder' => null,
            'latestInvoice' => null,
            'latestPayment' => null,
        ]);
    }

    public function store(SaveEventRequest $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('manage', $listing);

        $data = $request->validated();
        $this->ensurePublishableListing($data['status'], $listing);
        $data['listing_id'] = $listing->id;
        $data['user_id'] = $listing->user_id ?: $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['published_at'] = $this->publishedAt($data['status'], $data['published_at'] ?? null);
        $data['is_all_day'] = $request->boolean('is_all_day');
        $data = $this->handleUploads($request, $data);

        $event = Event::create($this->eventAttributes($data));
        $event->categories()->sync($data['category_ids'] ?? []);

        return redirect()
            ->route('account.listings.events.edit', [$listing, $event])
            ->with('status', 'Event saved.');
    }

    public function edit(Request $request, Listing $listing, Event $event): View
    {
        Gate::authorize('manage', $listing);
        abort_unless($event->listing_id === $listing->id, 404);
        $event->load('categories');
        $event->load([
            'activeSubscription.package',
            'orderItems.order.invoices',
            'orderItems.order.payments',
            'orderItems.package',
        ]);

        $latestOrderItem = $event->orderItems->sortByDesc('id')->first();
        $latestOrder = $latestOrderItem?->order;
        $latestInvoice = $latestOrder?->latestInvoice();
        $latestPayment = $latestOrder?->latestPayment();

        return view('account.events.form', [
            'listing' => $listing,
            'event' => $event,
            'categories' => Category::where('type', 'event')->orderBy('name')->get(),
            'selectedCategoryIds' => $event->categories->modelKeys(),
            'pageTitle' => 'Edit Event',
            'formAction' => route('account.listings.events.update', [$listing, $event]),
            'formMethod' => 'PUT',
            'latestOrderItem' => $latestOrderItem,
            'latestOrder' => $latestOrder,
            'latestInvoice' => $latestInvoice,
            'latestPayment' => $latestPayment,
        ]);
    }

    public function update(SaveEventRequest $request, Listing $listing, Event $event): RedirectResponse
    {
        Gate::authorize('manage', $listing);
        abort_unless($event->listing_id === $listing->id, 404);

        $data = $request->validated();
        $this->ensurePublishableListing($data['status'], $listing);
        $data['published_at'] = $this->publishedAt($data['status'], $data['published_at'] ?? $event->published_at);
        $data['is_all_day'] = $request->boolean('is_all_day');
        $data = $this->handleUploads($request, $data, $event);

        $event->update($this->eventAttributes($data));
        $event->categories()->sync($data['category_ids'] ?? []);

        return redirect()
            ->route('account.listings.events.edit', [$listing, $event])
            ->with('status', 'Event updated.');
    }

    public function destroy(Request $request, Listing $listing, Event $event): RedirectResponse
    {
        Gate::authorize('manage', $listing);
        abort_unless($event->listing_id === $listing->id, 404);

        if ($event->featured_image) {
            Storage::disk('public')->delete($event->featured_image);
        }

        $event->delete();

        return redirect()
            ->route('account.listings.events.index', $listing)
            ->with('status', 'Event removed.');
    }

    private function eventAttributes(array $data): array
    {
        return Arr::except($data, [
            'category_ids',
            'featured_image_upload',
            'remove_featured_image',
        ]);
    }

    private function publishedAt(string $status, mixed $publishedAt): mixed
    {
        if ($status !== 'published') {
            return null;
        }

        return $publishedAt ?: now();
    }

    private function handleUploads(Request $request, array $data, ?Event $event = null): array
    {
        if ($request->boolean('remove_featured_image') && $event?->featured_image) {
            $this->deleteFile($event->featured_image);
            $data['featured_image'] = null;
        } elseif ($request->hasFile('featured_image_upload')) {
            $this->deleteFile($event?->featured_image);
            $data['featured_image'] = $this->storeImage($request->file('featured_image_upload'), 'events/featured');
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

    private function ensurePublishableListing(string $status, Listing $listing): void
    {
        if ($status !== 'published') {
            return;
        }

        if (! $listing->hasActiveBusinessEntitlement()) {
            throw ValidationException::withMessages([
                'status' => 'Published events require the linked business listing to have an active package.',
            ]);
        }
    }

    private function uniqueSlug(string $title, ?Event $event = null): string
    {
        $base = Str::slug($title);
        $slug = $base !== '' ? $base : 'event';
        $suffix = 1;

        while (
            Event::query()
                ->where('slug', $slug)
                ->when($event, fn ($query) => $query->whereKeyNot($event->id))
                ->exists()
        ) {
            $slug = ($base !== '' ? $base : 'event').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

}
