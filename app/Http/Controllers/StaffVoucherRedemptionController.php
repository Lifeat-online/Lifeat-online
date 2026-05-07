<?php

namespace App\Http\Controllers;

use App\Models\VoucherRedemption;
use App\Services\VoucherRedemptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffVoucherRedemptionController extends Controller
{
    public function __construct(private readonly VoucherRedemptionService $voucherRedemptionService)
    {
    }

    public function show(Request $request): View
    {
        $code = trim((string) $request->string('code'));

        $redemption = null;

        if ($code !== '') {
            $redemption = VoucherRedemption::with(['voucher.listing.owner', 'customer'])
                ->where('code', $code)
                ->first();

            if ($redemption && ! $this->canManage($request, $redemption)) {
                $redemption = null;
            }
        }

        return view('staff.vouchers.redeem', [
            'code' => $code,
            'redemption' => $redemption,
        ]);
    }

    public function consume(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $redemption = VoucherRedemption::with(['voucher.listing.owner'])->where('code', $data['code'])->first();

        if ($redemption && ! $this->canManage($request, $redemption)) {
            return redirect()
                ->route('staff.vouchers.redeem')
                ->withErrors(['code' => 'You are not authorised to consume this voucher.']);
        }

        try {
            $consumed = $this->voucherRedemptionService->consume(
                $data['code'],
                $request->user(),
                (string) $request->ip(),
                (string) $request->userAgent()
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('staff.vouchers.redeem', ['code' => $data['code']])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('staff.vouchers.redeem', ['code' => $consumed->code])
            ->with('status', 'Voucher consumed successfully.');
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

