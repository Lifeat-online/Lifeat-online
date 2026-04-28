<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public function index(): View
    {
        return view('admin.events.index', [
            'events' => Event::with(['listing', 'categories'])->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.events.form', [
            'event' => new Event(),
            'listings' => Listing::orderBy('title')->get(),
            'categories' => Category::where('type', 'event')->orderBy('name')->get(),
            'selectedCategoryIds' => [],
            'pageTitle' => 'Create Event',
            'formAction' => route('admin.events.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->ensurePublishableListing($data['status'], $data['listing_id'] ?? null);
        $data['user_id'] = $request->user()->id;
        $data['published_at'] = $this->publishedAt($data['status'], $data['published_at'] ?? null);
        $data['is_all_day'] = $request->boolean('is_all_day');
        $data = $this->handleUploads($request, $data);

        $event = Event::create($data);
        $event->categories()->sync($request->input('category_ids', []));

        return redirect()->route('admin.events.edit', $event)->with('status', 'Event saved.');
    }

    public function edit(Event $event): View
    {
        $event->load('categories');

        return view('admin.events.form', [
            'event' => $event,
            'listings' => Listing::orderBy('title')->get(),
            'categories' => Category::where('type', 'event')->orderBy('name')->get(),
            'selectedCategoryIds' => $event->categories->modelKeys(),
            'pageTitle' => 'Edit Event',
            'formAction' => route('admin.events.update', $event),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validated($request, $event);
        $this->ensurePublishableListing($data['status'], $data['listing_id'] ?? null);
        $data['published_at'] = $this->publishedAt($data['status'], $data['published_at'] ?? $event->published_at);
        $data['is_all_day'] = $request->boolean('is_all_day');
        $data = $this->handleUploads($request, $data, $event);

        $event->update($data);
        $event->categories()->sync($request->input('category_ids', []));

        return redirect()->route('admin.events.edit', $event)->with('status', 'Event updated.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->deleteFile($event->featured_image);
        $event->delete();

        return redirect()->route('admin.events.index')->with('status', 'Event deleted.');
    }

    private function validated(Request $request, ?Event $event = null): array
    {
        return $request->validate([
            'listing_id' => ['nullable', 'integer', 'exists:listings,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('events', 'slug')->ignore($event?->id)],
            'excerpt' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'website_url' => ['nullable', 'url'],
            'featured_image_upload' => ['nullable', 'image', 'max:5120'],
            'remove_featured_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
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

    private function ensurePublishableListing(string $status, ?int $listingId): void
    {
        if ($status !== 'published') {
            return;
        }

        if (! $listingId) {
            throw ValidationException::withMessages([
                'listing_id' => 'Published events require a linked business listing with an active package.',
            ]);
        }

        $listing = Listing::find($listingId);

        if (! $listing || ! $listing->hasActiveBusinessEntitlement()) {
            throw ValidationException::withMessages([
                'listing_id' => 'The selected listing does not have an active business entitlement for event publishing.',
            ]);
        }
    }
}
