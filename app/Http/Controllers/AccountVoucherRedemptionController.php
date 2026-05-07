<?php

namespace App\Http\Controllers;

use App\Models\VoucherRedemption;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountVoucherRedemptionController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.vouchers.index', [
            'redemptions' => VoucherRedemption::with(['voucher.listing'])
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->paginate(20),
        ]);
    }
}

