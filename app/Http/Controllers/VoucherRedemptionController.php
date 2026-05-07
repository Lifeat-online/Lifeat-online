<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Voucher;
use App\Services\VoucherRedemptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VoucherRedemptionController extends Controller
{
    public function __construct(private readonly VoucherRedemptionService $voucherRedemptionService)
    {
    }

    public function store(Request $request, Listing $listing, Voucher $voucher): RedirectResponse
    {
        abort_if($listing->status !== 'published', 404);
        abort_unless($voucher->listing_id === $listing->id, 404);

        try {
            $redemption = $this->voucherRedemptionService->claim($voucher, $request->user());
        } catch (ValidationException $exception) {
            return redirect()
                ->route('vouchers.show', [$listing, $voucher])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('account.vouchers.index')
            ->with('status', 'Voucher redeemed. Your code is '.$redemption->code.'.');
    }
}
