<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherApiController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('q'));
        $categoryId = $request->integer('category_id');
        $sort = trim((string) $request->string('sort', 'newest'));
        $listingSlug = trim((string) $request->string('listing'));

        $query = Voucher::query()
            ->with(['listing', 'categories'])
            ->active()
            ->whereHas('listing', fn ($listing) => $listing->where('status', 'published'))
            ->when($listingSlug !== '', function ($query) use ($listingSlug) {
                $query->whereHas('listing', fn ($listing) => $listing->where('slug', $listingSlug));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('listing', fn ($listing) => $listing->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($categoryId > 0, function ($query) use ($categoryId) {
                $query->whereHas('categories', fn ($categories) => $categories->where('categories.id', $categoryId));
            })
            ->when($sort === 'ending', fn ($q) => $q->orderByRaw('end_at is null')->orderBy('end_at'))
            ->when($sort === 'popular', fn ($q) => $q->orderByDesc('redemptions_count'))
            ->when($sort === 'newest', fn ($q) => $q->orderByDesc('published_at')->orderByDesc('id'));

        return response()->json([
            'data' => $query->paginate(12)->withQueryString(),
        ]);
    }

    public function show(Listing $listing, Voucher $voucher)
    {
        abort_if($listing->status !== 'published', 404);
        abort_unless($voucher->listing_id === $listing->id, 404);

        $voucher->load(['listing', 'categories']);

        return response()->json([
            'data' => [
                'voucher' => $voucher,
                'is_active' => $voucher->isCurrentlyActive() && $listing->status === 'published',
                'remaining_uses' => $voucher->remainingUses(),
            ],
        ]);
    }
}
