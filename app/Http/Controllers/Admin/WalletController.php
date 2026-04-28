<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffWallet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        $wallets = StaffWallet::with(['user', 'payoutRequests' => fn ($q) => $q->whereIn('status', ['requested', 'approved'])])
            ->withCount('ledgerEntries')
            ->orderByDesc('available_balance')
            ->paginate(20)
            ->withQueryString();

        return view('admin.wallet.index', [
            'wallets' => $wallets,
        ]);
    }

    public function show(StaffWallet $staffWallet): View
    {
        $staffWallet->load([
            'user',
            'ledgerEntries' => fn ($q) => $q->latest('recorded_at'),
            'payoutRequests' => fn ($q) => $q->latest('id'),
        ]);

        return view('admin.wallet.show', [
            'wallet'         => $staffWallet,
            'ledgerEntries'  => $staffWallet->ledgerEntries,
            'payoutRequests' => $staffWallet->payoutRequests,
        ]);
    }
}
