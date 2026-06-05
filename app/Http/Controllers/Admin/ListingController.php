<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Services\AiContentAssistantService;
use App\Services\AuditLogService;
use App\Support\Validation\UploadRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ListingController extends Controller
{
    public function index(Request $request, AiContentAssistantService $assistant)
    {
        $status = $request->string('status')->toString();
        $featured = $request->string('featured')->toString();
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->toString() ?: 'newest';

        $query = Listing::query()
            ->with(['categories', 'activeSubscription'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($featured !== '', fn ($q) => $q->where('is_featured', $featured === 'yes'))
            ->when($search !== '', function ($q) use ($search) {
                $needle = mb_substr($search, 0, 120);
                $q->where(function ($inner) use ($needle) {
                    $inner->where('title', 'like', "%{$needle}%")
                        ->orWhere('slug', 'like', "%{$needle}%")
                        ->orWhere('city', 'like', "%{$needle}%")
                        ->orWhere('email', 'like', "%{$needle}%")
                        ->orWhere('phone', 'like', "%{$needle}%");
                });
            });

        $query->orderBy(match ($sort) {
            'oldest' => 'created_at',
            'title_asc' => 'title',
            'title_desc' => 'title',
            default => 'created_at',
        }, in_array($sort, ['oldest', 'title_asc'], true) ? 'asc' : 'desc');

        $listings = $query->paginate(15)->withQueryString();
        $qualityScores = $listings->getCollection()
            ->mapWithKeys(fn (Listing $listing): array => [$listing->id => $assistant->listingQualityScore($listing)])
            ->all();

        if ($request->expectsJson()) {
            return response()->json($listings);
        }

        return view('admin.listings.index', [
            'listings' => $listings,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'featured' => $featured,
                'sort' => $sort,
            ],
            'qualityScores' => $qualityScores,
        ]);
    }

    public function show(Request $request, Listing $listing)
    {
        $listing->load(['categories', 'owner', 'marketingIntegrations', 'vouchers']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'listing' => $listing]);
        }

        return redirect()->route('admin.listings.edit', $listing);
    }

    public function create(Request $request, AiContentAssistantService $assistant): View
    {
        $listing = new Listing();

        return view('admin.listings.form', [
            'listing' => $listing,
            'categories' => Category::where('type', 'listing')->orderBy('name')->get(),
            'selectedCategoryIds' => [],
            'ownerOptions' => $this->ownerOptions(),
            'canManageOwner' => $this->canManageListingOwner($request),
            'qualityScore' => $assistant->listingQualityScore($listing),
            'pageTitle' => 'Create Listing',
            'formAction' => route('admin.listings.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $data = $this->validated($request);
        $data['user_id'] = $this->ownerUserIdFor($request) ?? $request->user()->id;
        $data['registered_by_user_id'] = $request->user()->hasRole('staff') ? $request->user()->id : null;
        $data['published_at'] = $this->publishedAt($data['status'], $data['published_at'] ?? null);
        $data['is_featured'] = $request->boolean('is_featured');
        $data = $this->handleUploads($request, $data);

        $listing = Listing::create($data);
        $listing->categories()->sync($request->input('category_ids', []));
        $audit->log($request, 'listing.created', $listing, [], $listing->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'listing' => $listing->fresh()->load('categories')], 201);
        }

        return redirect()->route('admin.listings.edit', $listing)->with('status', 'Listing saved.');
    }

    public function edit(Request $request, Listing $listing, AiContentAssistantService $assistant): View
    {
        $listing->load(['categories', 'activeSubscription']);

        return view('admin.listings.form', [
            'listing' => $listing,
            'categories' => Category::where('type', 'listing')->orderBy('name')->get(),
            'selectedCategoryIds' => $listing->categories->modelKeys(),
            'ownerOptions' => $this->ownerOptions($listing->user_id),
            'canManageOwner' => $this->canManageListingOwner($request),
            'qualityScore' => $assistant->listingQualityScore($listing),
            'pageTitle' => 'Edit Listing',
            'formAction' => route('admin.listings.update', $listing),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, Listing $listing, AuditLogService $audit)
    {
        $before = $listing->toArray();
        $previousOwner = $this->ownerSnapshot((int) $listing->user_id);

        $data = $this->validated($request, $listing);
        $data['user_id'] = $this->ownerUserIdFor($request, $listing) ?? $listing->user_id;
        $data['published_at'] = $this->publishedAt($data['status'], $data['published_at'] ?? $listing->published_at);
        $data['is_featured'] = $request->boolean('is_featured');
        $data = $this->handleUploads($request, $data, $listing);
        $ownerChanged = (int) $data['user_id'] !== (int) $listing->user_id;

        DB::transaction(function () use ($request, $listing, $data, $audit, $before, $ownerChanged, $previousOwner) {
            $listing->update($data);
            $listing->categories()->sync($request->input('category_ids', []));

            if ($ownerChanged) {
                $this->syncListingScopedOwners($listing, (int) $data['user_id']);
            }

            $fresh = $listing->fresh();
            $audit->log($request, 'listing.updated', $listing, $before, $fresh->toArray());

            if ($ownerChanged) {
                $audit->log($request, 'listing.ownership_transferred', $listing, $previousOwner, $this->ownerSnapshot((int) $fresh->user_id));
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'listing' => $listing->fresh()->load('categories')]);
        }

        return redirect()->route('admin.listings.edit', $listing)->with('status', 'Listing updated.');
    }

    public function destroy(Request $request, Listing $listing, AuditLogService $audit)
    {
        $before = $listing->toArray();
        $audit->log($request, 'listing.deleted', $listing, $before, []);
        $this->deleteListingFiles($listing);
        $listing->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin.listings.index')->with('status', 'Listing deleted.');
    }

    public function bulk(Request $request, AuditLogService $audit)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['publish', 'unpublish', 'feature', 'unfeature', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        $ids = collect($validated['ids'])->filter()->unique()->values()->all();

        $targets = Listing::query()->whereIn('slug', $ids)->get();

        foreach ($targets as $listing) {
            $before = $listing->toArray();

            match ($validated['action']) {
                'publish' => $listing->update(['status' => 'published', 'published_at' => $listing->published_at ?: now()]),
                'unpublish' => $listing->update(['status' => 'draft', 'published_at' => null]),
                'feature' => $listing->update(['is_featured' => true]),
                'unfeature' => $listing->update(['is_featured' => false]),
                'delete' => (function () use ($listing) {
                    $this->deleteListingFiles($listing);
                    $listing->delete();
                })(),
            };

            $audit->log($request, 'listing.bulk_'.$validated['action'], $listing, $before, $listing->fresh()?->toArray() ?? []);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'affected' => $targets->count()]);
        }

        return redirect()->route('admin.listings.index')->with('status', 'Bulk operation completed.');
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
            'featured_image_upload' => UploadRules::optionalPublicImage(),
            'logo_upload' => UploadRules::optionalPublicImage(),
            'remove_featured_image' => ['nullable', 'boolean'],
            'remove_logo' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);
    }

    private function ownerUserIdFor(Request $request, ?Listing $listing = null): ?int
    {
        if (! $this->canManageListingOwner($request)) {
            return $listing?->user_id ?? $request->user()?->id;
        }

        $validated = $request->validate([
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return filled($validated['owner_user_id'] ?? null)
            ? (int) $validated['owner_user_id']
            : ($listing?->user_id ?? $request->user()?->id);
    }

    private function canManageListingOwner(Request $request): bool
    {
        return $request->user()?->hasRole('admin', 'editor') ?? false;
    }

    private function ownerOptions(?int $currentOwnerId = null)
    {
        return User::query()
            ->where(function ($query) use ($currentOwnerId) {
                $query->whereIn('role', ['business_owner', 'staff', 'admin', 'super_admin']);

                if ($currentOwnerId) {
                    $query->orWhere('id', $currentOwnerId);
                }
            })
            ->orderBy('name')
            ->orderBy('email')
            ->limit(200)
            ->get(['id', 'name', 'email', 'role']);
    }

    private function ownerSnapshot(int $ownerId): array
    {
        $owner = User::query()->find($ownerId);

        return [
            'user_id' => $ownerId,
            'owner_name' => $owner?->name,
            'owner_email' => $owner?->email,
            'owner_role' => $owner?->role,
        ];
    }

    private function syncListingScopedOwners(Listing $listing, int $ownerUserId): void
    {
        $listing->events()->update(['user_id' => $ownerUserId]);
        $listing->adCampaigns()->update(['user_id' => $ownerUserId]);
        $listing->pushCampaigns()->update(['user_id' => $ownerUserId]);
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
