<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Voucher;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $listingId = $request->integer('listing_id');
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->toString() ?: 'newest';

        $query = Voucher::query()
            ->with(['listing', 'creator'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($listingId > 0, fn ($q) => $q->where('listing_id', $listingId))
            ->when($search !== '', function ($q) use ($search) {
                $needle = mb_substr($search, 0, 120);
                $q->where(function ($inner) use ($needle) {
                    $inner->where('title', 'like', "%{$needle}%")
                        ->orWhere('slug', 'like', "%{$needle}%")
                        ->orWhereHas('listing', fn ($l) => $l->where('title', 'like', "%{$needle}%"));
                });
            });

        $query->orderBy(match ($sort) {
            'oldest' => 'created_at',
            default => 'created_at',
        }, $sort === 'oldest' ? 'asc' : 'desc');

        $vouchers = $query->paginate(20)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'vouchers' => $vouchers]);
        }

        return view('admin.vouchers.index', [
            'vouchers' => $vouchers,
            'listings' => Listing::query()->orderBy('title')->limit(200)->get(['id', 'title', 'status']),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'listing_id' => $listingId ?: null,
                'sort' => $sort,
            ],
        ]);
    }

    public function show(Request $request, Voucher $voucher)
    {
        $voucher->load(['listing', 'creator', 'categories']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'voucher' => $voucher]);
        }

        return redirect()->route('admin.vouchers.edit', $voucher->getKey());
    }

    public function create(): View
    {
        return view('admin.vouchers.form', [
            'voucher' => new Voucher(),
            'listings' => Listing::query()->orderBy('title')->limit(200)->get(['id', 'title', 'status']),
            'pageTitle' => 'Create Voucher',
            'formAction' => route('admin.vouchers.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $data = $this->validated($request);

        $voucher = Voucher::create([
            ...$data,
            'created_by_user_id' => $request->user()?->id,
            'slug' => Voucher::uniqueSlugForListing((int) $data['listing_id'], (string) $data['title']),
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);

        $audit->log($request, 'voucher.created', $voucher, [], $voucher->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'voucher' => $voucher->fresh()->load('listing')], 201);
        }

        return redirect()->route('admin.vouchers.edit', $voucher->id)->with('status', 'Voucher saved.');
    }

    public function edit(int $voucher): View
    {
        $voucherModel = Voucher::query()->whereKey($voucher)->firstOrFail();

        return view('admin.vouchers.form', [
            'voucher' => $voucherModel,
            'listings' => Listing::query()->orderBy('title')->limit(200)->get(['id', 'title', 'status']),
            'pageTitle' => 'Edit Voucher',
            'formAction' => route('admin.vouchers.update', $voucherModel->id),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, int $voucher, AuditLogService $audit)
    {
        $voucherModel = Voucher::query()->whereKey($voucher)->firstOrFail();
        $before = $voucherModel->toArray();

        $data = $this->validated($request, $voucherModel);

        $voucherModel->update([
            ...$data,
            'slug' => Voucher::uniqueSlugForListing((int) $data['listing_id'], (string) $data['title'], $voucherModel),
            'published_at' => $data['status'] === 'published' ? ($voucherModel->published_at ?: now()) : null,
        ]);

        $audit->log($request, 'voucher.updated', $voucherModel, $before, $voucherModel->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'voucher' => $voucherModel->fresh()->load('listing')]);
        }

        return redirect()->route('admin.vouchers.edit', $voucherModel->id)->with('status', 'Voucher updated.');
    }

    public function destroy(Request $request, int $voucher, AuditLogService $audit)
    {
        $voucherModel = Voucher::query()->whereKey($voucher)->firstOrFail();
        $before = $voucherModel->toArray();

        $audit->log($request, 'voucher.deleted', $voucherModel, $before, []);
        $voucherModel->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin.vouchers.index')->with('status', 'Voucher deleted.');
    }

    public function bulk(Request $request, AuditLogService $audit)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['publish', 'unpublish', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:vouchers,id'],
        ]);

        $targets = Voucher::query()->whereIn('id', $validated['ids'])->get();

        foreach ($targets as $voucher) {
            $before = $voucher->toArray();

            match ($validated['action']) {
                'publish' => $voucher->update(['status' => 'published', 'published_at' => $voucher->published_at ?: now()]),
                'unpublish' => $voucher->update(['status' => 'draft', 'published_at' => null]),
                'delete' => $voucher->delete(),
            };

            $audit->log($request, 'voucher.bulk_'.$validated['action'], $voucher, $before, $voucher->fresh()?->toArray() ?? []);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'affected' => $targets->count()]);
        }

        return redirect()->route('admin.vouchers.index')->with('status', 'Bulk operation completed.');
    }

    private function validated(Request $request, ?Voucher $voucher = null): array
    {
        $data = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'voucher_type' => ['required', Rule::in([
                Voucher::TYPE_DISCOUNT_AMOUNT,
                Voucher::TYPE_DISCOUNT_PERCENT,
                Voucher::TYPE_FIXED_PRICE,
                Voucher::TYPE_PROMO_OFFER,
            ])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['nullable', 'string', 'max:8'],
            'usage_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'terms' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);

        if (in_array($data['voucher_type'], [Voucher::TYPE_DISCOUNT_AMOUNT, Voucher::TYPE_FIXED_PRICE], true) && empty($data['discount_amount'])) {
            abort(422, 'discount_amount is required for this voucher type.');
        }
        if ($data['voucher_type'] === Voucher::TYPE_DISCOUNT_PERCENT && empty($data['discount_percent'])) {
            abort(422, 'discount_percent is required for this voucher type.');
        }

        return $data;
    }
}
