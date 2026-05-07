<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Services\VoucherRedemptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VoucherRedemptionApiController extends Controller
{
    public function __construct(private readonly VoucherRedemptionService $voucherRedemptionService)
    {
    }

    public function redeem(Request $request, Listing $listing, Voucher $voucher)
    {
        abort_if($listing->status !== 'published', 404);
        abort_unless($voucher->listing_id === $listing->id, 404);

        try {
            $redemption = $this->voucherRedemptionService->claim($voucher, $request->user());
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        }

        return response()->json(['data' => $redemption], 201);
    }

    public function mine(Request $request)
    {
        return response()->json([
            'data' => VoucherRedemption::with(['voucher.listing'])
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function consume(Request $request, string $code)
    {
        $redemption = VoucherRedemption::with(['voucher.listing.owner'])->where('code', $code)->first();

        if ($redemption && ! $this->canManage($request, $redemption)) {
            return response()->json(['errors' => ['code' => ['Not authorised.']]], 403);
        }

        try {
            $consumed = $this->voucherRedemptionService->consume(
                $code,
                $request->user(),
                (string) $request->ip(),
                (string) $request->userAgent()
            );
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        }

        return response()->json(['data' => $consumed]);
    }

    private function canManage(Request $request, VoucherRedemption $redemption): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin', 'staff')) {
            return true;
        }

        return (int) $redemption->voucher->listing->user_id === (int) $user->id;
    }
}
