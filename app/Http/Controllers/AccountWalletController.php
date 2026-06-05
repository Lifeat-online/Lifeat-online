<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\RequestPayoutRequest;
use App\Models\AuditLog;
use App\Models\PayoutRequest;
use App\Models\StaffWallet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountWalletController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $wallet = StaffWallet::with([
            'ledgerEntries' => fn ($q) => $q->latest('recorded_at')->limit(50),
            'payoutRequests' => fn ($q) => $q->latest('id')->limit(10),
        ])->firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => 'ZAR']
        );

        Gate::authorize('view', $wallet);

        return view('account.wallet.index', [
            'wallet' => $wallet,
            'ledgerEntries' => $wallet->ledgerEntries->sortByDesc('recorded_at')->values(),
            'payoutRequests' => $wallet->payoutRequests->sortByDesc('id')->values(),
            'pendingRequest' => $wallet->pendingPayoutRequest(),
        ]);
    }

    public function requestPayout(Request $request, RequestPayoutRequest $payoutRequest): RedirectResponse
    {
        $user = $request->user();

        $wallet = StaffWallet::firstOrCreate(['user_id' => $user->id], ['currency' => 'ZAR']);

        Gate::authorize('requestPayout', $wallet);

        abort_if($wallet->pendingPayoutRequest(), 422, 'You already have an active payout request.');

        $validated = $payoutRequest->validated();

        $payout = PayoutRequest::create([
            'wallet_id'            => $wallet->id,
            'requested_by_user_id' => $user->id,
            'amount'               => $validated['amount'],
            'currency'             => $wallet->currency,
            'status'               => PayoutRequest::STATUS_REQUESTED,
            'bank_name'            => $validated['bank_name'],
            'account_holder'       => $validated['account_holder'],
            'account_number'       => $validated['account_number'],
            'branch_code'          => $validated['branch_code'],
            'notes'                => $validated['notes'] ?? null,
            'requested_at'         => now(),
        ]);

        AuditLog::create([
            'actor_user_id' => $user->id,
            'action'        => 'payout_request.submitted',
            'subject_type'  => PayoutRequest::class,
            'subject_id'    => $payout->id,
            'before_json'   => [],
            'after_json'    => ['amount' => $payout->amount, 'status' => $payout->status],
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        return redirect()->route('account.wallet.index')
            ->with('status', 'Payout request submitted. Admin will review it shortly.');
    }

    public function cancelPayout(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        Gate::authorize('cancel', $payoutRequest);

        abort_unless($payoutRequest->status === PayoutRequest::STATUS_REQUESTED, 422, 'Only pending requests can be cancelled.');

        $payoutRequest->update(['status' => PayoutRequest::STATUS_CANCELLED]);

        AuditLog::create([
            'actor_user_id' => $request->user()->id,
            'action'        => 'payout_request.cancelled_by_staff',
            'subject_type'  => PayoutRequest::class,
            'subject_id'    => $payoutRequest->id,
            'before_json'   => ['status' => PayoutRequest::STATUS_REQUESTED],
            'after_json'    => ['status' => PayoutRequest::STATUS_CANCELLED],
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        return redirect()->route('account.wallet.index')
            ->with('status', 'Payout request cancelled.');
    }
}
