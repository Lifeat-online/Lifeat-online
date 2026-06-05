<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffWallet;
use App\Models\WalletLedgerEntry;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StaffWallet::class);

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
        Gate::authorize('view', $staffWallet);

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

    public function adjust(Request $request, StaffWallet $staffWallet, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('adjust', $staffWallet);

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $wallet = DB::transaction(function () use ($request, $staffWallet, $validated, $audit): StaffWallet {
            $wallet = StaffWallet::query()
                ->whereKey($staffWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = round((float) $validated['amount'], 2);
            $signedAmount = $validated['direction'] === 'debit' ? -$amount : $amount;
            $before = $this->walletSnapshot($wallet);

            if ($signedAmount < 0 && ((float) $wallet->available_balance + $signedAmount) < 0) {
                abort(422, 'Adjustment debit cannot exceed the available balance.');
            }

            WalletLedgerEntry::create([
                'wallet_id' => $wallet->id,
                'entry_type' => WalletLedgerEntry::TYPE_ADJUSTMENT,
                'source_type' => StaffWallet::class,
                'source_id' => $wallet->id,
                'gross_amount' => $signedAmount,
                'net_amount' => $signedAmount,
                'currency' => $wallet->currency,
                'description' => 'Manual admin adjustment: '.$validated['reason'],
                'recorded_at' => now(),
            ]);

            $wallet->forceFill([
                'available_balance' => round((float) $wallet->available_balance + $signedAmount, 2),
            ])->save();

            $wallet->refresh();

            $audit->log($request, 'staff_wallet.adjusted', $wallet, $before, [
                ...$this->walletSnapshot($wallet),
                'direction' => $validated['direction'],
                'amount' => $amount,
                'reason' => $validated['reason'],
            ]);

            return $wallet;
        });

        return redirect()
            ->route('admin.wallet.show', $wallet)
            ->with('status', 'Wallet adjustment recorded.');
    }

    private function walletSnapshot(StaffWallet $wallet): array
    {
        return [
            'available_balance' => (string) $wallet->available_balance,
            'pending_balance' => (string) $wallet->pending_balance,
            'paid_out_total' => (string) $wallet->paid_out_total,
            'currency' => $wallet->currency,
        ];
    }
}
