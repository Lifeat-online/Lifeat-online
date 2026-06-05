<?php

namespace App\Http\Controllers\Admin;

use App\Events\PayoutPaid;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PayoutRequest;
use App\Services\StaffCommissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PayoutRequest::class);

        $status = $request->string('status')->toString();
        $walletId = $request->integer('wallet');

        $requests = $this->filteredQuery($request)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.payout-requests.index', [
            'requests'      => $requests,
            'selectedStatus' => $status,
            'statusOptions' => [
                PayoutRequest::STATUS_REQUESTED,
                PayoutRequest::STATUS_APPROVED,
                PayoutRequest::STATUS_PAID,
                PayoutRequest::STATUS_REJECTED,
                PayoutRequest::STATUS_CANCELLED,
            ],
            'pendingCount' => PayoutRequest::whereIn('status', PayoutRequest::activeStatuses())->count(),
            'selectedWalletId' => $walletId ?: null,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', PayoutRequest::class);

        $query = $this->filteredQuery($request)->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Payout ID',
                'Staff Member',
                'Staff Email',
                'Status',
                'Currency',
                'Amount',
                'Bank',
                'Account Holder',
                'Account Number',
                'Branch Code',
                'Payment Reference',
                'Requested At',
                'Reviewed At',
                'Paid At',
                'Reviewed By',
                'Ledger Debit Total',
                'Wallet Available Balance',
                'Wallet Paid Out Total',
                'Notes',
            ]);

            $query->chunkById(200, function ($requests) use ($handle) {
                foreach ($requests as $payout) {
                    fputcsv($handle, [
                        $payout->id,
                        $payout->requestedBy?->name,
                        $payout->requestedBy?->email,
                        $payout->status,
                        $payout->currency,
                        $payout->amount,
                        $payout->bank_name,
                        $payout->account_holder,
                        $payout->account_number,
                        $payout->branch_code,
                        $payout->payment_reference,
                        optional($payout->requested_at)->toDateTimeString(),
                        optional($payout->reviewed_at)->toDateTimeString(),
                        optional($payout->paid_at)->toDateTimeString(),
                        $payout->reviewedBy?->name,
                        $payout->ledgerEntries->sum('net_amount'),
                        $payout->wallet?->available_balance,
                        $payout->wallet?->paid_out_total,
                        $payout->notes,
                    ]);
                }
            });
        }, 'payout-requests-export.csv', ['Content-Type' => 'text/csv']);
    }

    public function show(PayoutRequest $payoutRequest): View
    {
        Gate::authorize('view', $payoutRequest);

        $payoutRequest->load(['requestedBy', 'reviewedBy', 'wallet.user', 'ledgerEntries']);

        return view('admin.payout-requests.show', [
            'payout' => $payoutRequest,
        ]);
    }

    public function approve(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        Gate::authorize('approve', $payoutRequest);

        abort_unless($payoutRequest->status === PayoutRequest::STATUS_REQUESTED, 422, 'Only pending requests can be approved.');

        $before = ['status' => $payoutRequest->status];

        DB::transaction(function () use ($payoutRequest, $request) {
            $locked = PayoutRequest::whereKey($payoutRequest->id)->lockForUpdate()->first();

            abort_unless($locked->status === PayoutRequest::STATUS_REQUESTED, 422, 'Only pending requests can be approved.');

            $locked->update([
                'status'             => PayoutRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at'        => now(),
            ]);
        });

        $payoutRequest->refresh();

        $this->logAudit($request, 'payout_request.approved', $payoutRequest, $before, ['status' => PayoutRequest::STATUS_APPROVED]);

        return redirect()->route('admin.payout-requests.show', $payoutRequest)
            ->with('status', 'Payout request approved.');
    }

    public function reject(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        Gate::authorize('reject', $payoutRequest);

        abort_unless($payoutRequest->status === PayoutRequest::STATUS_REQUESTED, 422, 'Only pending requests can be rejected.');

        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $before = ['status' => $payoutRequest->status];

        DB::transaction(function () use ($payoutRequest, $request, $validated) {
            $locked = PayoutRequest::whereKey($payoutRequest->id)->lockForUpdate()->first();

            abort_unless($locked->status === PayoutRequest::STATUS_REQUESTED, 422, 'Only pending requests can be rejected.');

            $locked->update([
                'status'              => PayoutRequest::STATUS_REJECTED,
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at'         => now(),
                'notes'               => $validated['notes'] ?? $locked->notes,
            ]);
        });

        $payoutRequest->refresh();

        $this->logAudit($request, 'payout_request.rejected', $payoutRequest, $before, ['status' => PayoutRequest::STATUS_REJECTED]);

        return redirect()->route('admin.payout-requests.show', $payoutRequest)
            ->with('status', 'Payout request rejected.');
    }

    public function markPaid(Request $request, PayoutRequest $payoutRequest, StaffCommissionService $commissionService): RedirectResponse
    {
        Gate::authorize('markPaid', $payoutRequest);

        abort_unless($payoutRequest->status === PayoutRequest::STATUS_APPROVED, 422, 'Only approved requests can be marked paid.');

        $validated = $request->validate(['payment_reference' => ['nullable', 'string', 'max:100']]);
        $before = ['status' => $payoutRequest->status];

        DB::transaction(function () use ($payoutRequest, $request, $validated) {
            $locked = PayoutRequest::whereKey($payoutRequest->id)->lockForUpdate()->first();

            abort_unless($locked->status === PayoutRequest::STATUS_APPROVED, 422, 'Only approved requests can be marked paid.');

            $locked->update([
                'status'            => PayoutRequest::STATUS_PAID,
                'payment_reference' => $validated['payment_reference'] ?? null,
                'paid_at'           => now(),
            ]);
        });

        $payoutRequest->refresh();

        $ledgerEntry = $commissionService->debitForPayout($payoutRequest);
        PayoutPaid::dispatch($payoutRequest->fresh(['wallet', 'requestedBy']), $ledgerEntry);

        $this->logAudit($request, 'payout_request.marked_paid', $payoutRequest, $before, [
            'status'            => PayoutRequest::STATUS_PAID,
            'payment_reference' => $payoutRequest->payment_reference,
        ]);

        return redirect()->route('admin.payout-requests.show', $payoutRequest)
            ->with('status', 'Payout marked as paid and wallet debited.');
    }

    private function logAudit(Request $request, string $action, PayoutRequest $subject, array $before, array $after): void
    {
        AuditLog::create([
            'actor_user_id' => $request->user()?->id,
            'action'        => $action,
            'subject_type'  => PayoutRequest::class,
            'subject_id'    => $subject->id,
            'before_json'   => $before,
            'after_json'    => $after,
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $status = $request->string('status')->toString();
        $walletId = $request->integer('wallet');

        return PayoutRequest::query()
            ->with(['requestedBy', 'reviewedBy', 'wallet.user', 'ledgerEntries'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($walletId > 0, fn ($query) => $query->where('wallet_id', $walletId));
    }
}
