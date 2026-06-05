<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\SaveVoucherRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Voucher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class AccountVoucherController extends Controller
{
    public function index(Request $request, Listing $listing): View
    {
        Gate::authorize('manage', $listing);

        $status = trim((string) $request->string('status'));

        return view('account.vouchers.manage.index', [
            'listing' => $listing,
            'vouchers' => Voucher::withCount([
                'redemptions as claimed_count' => fn ($q) => $q->where('status', 'claimed'),
                'redemptions as consumed_count' => fn ($q) => $q->where('status', 'consumed'),
            ])
                ->where('listing_id', $listing->id)
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['status' => $status],
        ]);
    }

    public function create(Request $request, Listing $listing): View
    {
        Gate::authorize('manage', $listing);

        return view('account.vouchers.manage.form', [
            'listing' => $listing,
            'voucher' => new Voucher([
                'listing_id' => $listing->id,
                'voucher_type' => Voucher::TYPE_DISCOUNT_AMOUNT,
                'usage_limit' => 1,
                'status' => 'draft',
                'currency' => 'ZAR',
            ]),
            'categories' => Category::where('type', 'listing')->orderBy('name')->get(),
            'selectedCategoryIds' => [],
            'pageTitle' => 'Create Voucher',
            'formAction' => route('account.listings.vouchers.store', $listing),
            'formMethod' => 'POST',
        ]);
    }

    public function store(SaveVoucherRequest $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('manage', $listing);

        $data = $request->validatedWithDefaults();
        $data['listing_id'] = $listing->id;
        $data['created_by_user_id'] = $request->user()->id;
        $data['slug'] = Voucher::uniqueSlugForListing($listing->id, $data['title']);
        $data = $this->normalizePublishing($data);

        $voucher = Voucher::create($this->voucherAttributes($data));
        $voucher->categories()->sync($data['category_ids'] ?? []);

        return redirect()
            ->route('account.listings.vouchers.edit', [$listing, $voucher])
            ->with('status', 'Voucher saved.');
    }

    public function edit(Request $request, Listing $listing, Voucher $voucher): View
    {
        Gate::authorize('manage', $listing);
        abort_unless($voucher->listing_id === $listing->id, 404);

        $voucher->load('categories');

        return view('account.vouchers.manage.form', [
            'listing' => $listing,
            'voucher' => $voucher,
            'categories' => Category::where('type', 'listing')->orderBy('name')->get(),
            'selectedCategoryIds' => $voucher->categories->modelKeys(),
            'pageTitle' => 'Edit Voucher',
            'formAction' => route('account.listings.vouchers.update', [$listing, $voucher]),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(SaveVoucherRequest $request, Listing $listing, Voucher $voucher): RedirectResponse
    {
        Gate::authorize('manage', $listing);
        abort_unless($voucher->listing_id === $listing->id, 404);

        $data = $request->validatedWithDefaults();
        $data['slug'] = Voucher::uniqueSlugForListing($listing->id, $data['title'], $voucher);
        $data = $this->normalizePublishing($data, $voucher);

        $voucher->update($this->voucherAttributes($data));
        $voucher->categories()->sync($data['category_ids'] ?? []);

        return redirect()
            ->route('account.listings.vouchers.edit', [$listing, $voucher])
            ->with('status', 'Voucher updated.');
    }

    public function destroy(Request $request, Listing $listing, Voucher $voucher): RedirectResponse
    {
        Gate::authorize('manage', $listing);
        abort_unless($voucher->listing_id === $listing->id, 404);

        $voucher->delete();

        return redirect()
            ->route('account.listings.vouchers.index', $listing)
            ->with('status', 'Voucher removed.');
    }

    public function dashboard(Request $request, Listing $listing): View
    {
        Gate::authorize('manage', $listing);

        $voucherIds = Voucher::query()->where('listing_id', $listing->id)->pluck('id');

        $recent = $voucherIds->isNotEmpty()
            ? \App\Models\VoucherRedemption::query()
                ->with('voucher')
                ->whereIn('voucher_id', $voucherIds->all())
                ->latest('id')
                ->limit(25)
                ->get()
            : collect();

        $totals = [
            'vouchers' => Voucher::where('listing_id', $listing->id)->count(),
            'published' => Voucher::where('listing_id', $listing->id)->where('status', 'published')->count(),
            'claimed' => \App\Models\VoucherRedemption::whereIn('voucher_id', $voucherIds->all())->where('status', 'claimed')->count(),
            'consumed' => \App\Models\VoucherRedemption::whereIn('voucher_id', $voucherIds->all())->where('status', 'consumed')->count(),
        ];

        return view('account.vouchers.manage.dashboard', [
            'listing' => $listing,
            'totals' => $totals,
            'recentRedemptions' => $recent,
        ]);
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

    private function voucherAttributes(array $data): array
    {
        return Arr::except($data, ['category_ids']);
    }

}
