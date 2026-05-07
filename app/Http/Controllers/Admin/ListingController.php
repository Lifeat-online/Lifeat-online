<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ListingController extends Controller
{
    public function index(): View
    {
        return view('admin.listings.index', [
            'listings' => Listing::with('categories')->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.listings.form', [
            'listing' => new Listing(),
            'categories' => Category::where('type', 'listing')->orderBy('name')->get(),
            'selectedCategoryIds' => [],
            'pageTitle' => 'Create Listing',
            'formAction' => route('admin.listings.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['registered_by_user_id'] = $request->user()->hasRole('staff') ? $request->user()->id : null;
        $data['published_at'] = $this->publishedAt($data['status'], $data['published_at'] ?? null);
        $data['is_featured'] = $request->boolean('is_featured');
        $data = $this->handleUploads($request, $data);

        $listing = Listing::create($data);
        $listing->categories()->sync($request->input('category_ids', []));

        return redirect()->route('admin.listings.edit', $listing)->with('status', 'Listing saved.');
    }

    public function edit(Listing $listing): View
    {
        $listing->load('categories');

        return view('admin.listings.form', [
            'listing' => $listing,
            'categories' => Category::where('type', 'listing')->orderBy('name')->get(),
            'selectedCategoryIds' => $listing->categories->modelKeys(),
            'pageTitle' => 'Edit Listing',
            'formAction' => route('admin.listings.update', $listing),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, Listing $listing): RedirectResponse
    {
        $data = $this->validated($request, $listing);
        $data['published_at'] = $this->publishedAt($data['status'], $data['published_at'] ?? $listing->published_at);
        $data['is_featured'] = $request->boolean('is_featured');
        $data = $this->handleUploads($request, $data, $listing);

        $listing->update($data);
        $listing->categories()->sync($request->input('category_ids', []));

        return redirect()->route('admin.listings.edit', $listing)->with('status', 'Listing updated.');
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        $this->deleteFile($listing->featured_image);
        $this->deleteFile($listing->logo_path);
        $listing->delete();

        return redirect()->route('admin.listings.index')->with('status', 'Listing deleted.');
    }

    private function validated(Request $request, ?Listing $listing = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('listings', 'slug')->ignore($listing?->id)],
            'excerpt' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'website_url' => ['nullable', 'url'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'featured_image_upload' => ['nullable', 'image', 'max:5120'],
            'logo_upload' => ['nullable', 'image', 'max:5120'],
            'remove_featured_image' => ['nullable', 'boolean'],
            'remove_logo' => ['nullable', 'boolean'],
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

    private function handleUploads(Request $request, array $data, ?Listing $listing = null): array
    {
        if ($request->boolean('remove_featured_image') && $listing?->featured_image) {
            $this->deleteFile($listing->featured_image);
            $data['featured_image'] = null;
        } elseif ($request->hasFile('featured_image_upload')) {
            $this->deleteFile($listing?->featured_image);
            $data['featured_image'] = $this->storeImage($request->file('featured_image_upload'), 'listings/featured');
        }

        if ($request->boolean('remove_logo') && $listing?->logo_path) {
            $this->deleteFile($listing->logo_path);
            $data['logo_path'] = null;
        } elseif ($request->hasFile('logo_upload')) {
            $this->deleteFile($listing?->logo_path);
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
}
