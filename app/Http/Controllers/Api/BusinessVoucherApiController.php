<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessVoucherApiController extends Controller
{
    public function index(Request $request, Listing $listing)
    {
        abort_unless($listing->user_id === $request->user()->id, 403);

        return response()->json([
            'data' => Voucher::withCount([
                'redemptions as claimed_count' => fn ($q) => $q->where('status', 'claimed'),
                'redemptions as consumed_count' => fn ($q) => $q->where('status', 'consumed'),
            ])
                ->where('listing_id', $listing->id)
                ->orderByDesc('id')
                ->paginate(15),
        ]);
    }

    public function store(Request $request, Listing $listing)
    {
        abort_unless($listing->user_id === $request->user()->id, 403);

        $data = $this->validated($request);
        $data['listing_id'] = $listing->id;
        $data['created_by_user_id'] = $request->user()->id;
        $data['slug'] = Voucher::uniqueSlugForListing($listing->id, $data['title']);
        $data = $this->normalizePublishing($data);

        $voucher = Voucher::create($data);
        $voucher->categories()->sync($request->input('category_ids', []));

        return response()->json(['data' => $voucher->load('categories')], 201);
    }

    public function update(Request $request, Listing $listing, Voucher $voucher)
    {
        abort_unless($listing->user_id === $request->user()->id, 403);
        abort_unless($voucher->listing_id === $listing->id, 404);

        $data = $this->validated($request);
        $data['slug'] = Voucher::uniqueSlugForListing($listing->id, $data['title'], $voucher);
        $data = $this->normalizePublishing($data, $voucher);

        $voucher->update($data);
        $voucher->categories()->sync($request->input('category_ids', []));

        return response()->json(['data' => $voucher->fresh(['categories'])]);
    }

    public function destroy(Request $request, Listing $listing, Voucher $voucher)
    {
        abort_unless($listing->user_id === $request->user()->id, 403);
        abort_unless($voucher->listing_id === $listing->id, 404);

        $voucher->delete();

        return response()->json(['ok' => true]);
    }

    public function stats(Request $request, Listing $listing)
    {
        abort_unless($listing->user_id === $request->user()->id, 403);

        $voucherIds = Voucher::query()->where('listing_id', $listing->id)->pluck('id');

        $totals = [
            'vouchers' => Voucher::where('listing_id', $listing->id)->count(),
            'published' => Voucher::where('listing_id', $listing->id)->where('status', 'published')->count(),
            'claimed' => VoucherRedemption::whereIn('voucher_id', $voucherIds->all())->where('status', 'claimed')->count(),
            'consumed' => VoucherRedemption::whereIn('voucher_id', $voucherIds->all())->where('status', 'consumed')->count(),
        ];

        return response()->json(['data' => $totals]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'voucher_type' => ['required', Rule::in([
                Voucher::TYPE_DISCOUNT_AMOUNT,
                Voucher::TYPE_DISCOUNT_PERCENT,
                Voucher::TYPE_FIXED_PRICE,
                Voucher::TYPE_PROMO_OFFER,
            ])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'usage_limit' => ['required', 'integer', 'min:1'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'terms' => ['nullable', 'string', 'max:6000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')->where('type', 'listing')],
        ]);

        if (in_array($data['voucher_type'], [Voucher::TYPE_DISCOUNT_AMOUNT, Voucher::TYPE_FIXED_PRICE], true) && ($data['discount_amount'] ?? null) === null) {
            $data['discount_amount'] = 0;
        }

        if ($data['voucher_type'] === Voucher::TYPE_DISCOUNT_PERCENT && ($data['discount_percent'] ?? null) === null) {
            $data['discount_percent'] = 0;
        }

        return $data;
    }

    private function normalizePublishing(array $data, ?Voucher $existing = null): array
    {
        if (($data['status'] ?? null) === 'published') {
            $data['published_at'] = $existing?->published_at ?: now();
        } else {
            $data['published_at'] = null;
        }

        return $data;
    }
}

