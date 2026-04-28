<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PayoutRequest;
use App\Services\StaffCommissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayoutRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $requests = PayoutRequest::with(['requestedBy', 'reviewedBy', 'wallet.user'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
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
        ]);
    }

    public function show(PayoutRequest $payoutRequest): View
    {
        $payoutRequest->load(['requestedBy', 'reviewedBy', 'wallet.user', 'ledgerEntries']);

        return view('admin.payout-requests.show', [
            'payout' => $payoutRequest,
        ]);
    }

    public function approve(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        abort_unless($payoutRequest->status === PayoutRequest::STATUS_REQUESTED, 422, 'Only pending requests can be approved.');

        $before = ['status' => $payoutRequest->status];
        $payoutRequest->update([
            'status'             => PayoutRequest::STATUS_APPROVED,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at'        => now(),
        ]);

        $this->logAudit($request, 'payout_request.approved', $payoutRequest, $before, ['status' => PayoutRequest::STATUS_APPROVED]);

        return redirect()->route('admin.payout-requests.show', $payoutRequest)
            ->with('status', 'Payout request approved.');
    }

    public function reject(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        abort_unless($payoutRequest->status === PayoutRequest::STATUS_REQUESTED, 422, 'Only pending requests can be rejected.');

        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $before = ['status' => $payoutRequest->status];

        $payoutRequest->update([
            'status'              => PayoutRequest::STATUS_REJECTED,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at'         => now(),
            'notes'               => $validated['notes'] ?? $payoutRequest->notes,
        ]);

        $this->logAudit($request, 'payout_request.rejected', $payoutRequest, $before, ['status' => PayoutRequest::STATUS_REJECTED]);

        return redirect()->route('admin.payout-requests.show', $payoutRequest)
            ->with('status', 'Payout request rejected.');
    }

    public function markPaid(Request $request, PayoutRequest $payoutRequest, StaffCommissionService $commissionService): RedirectResponse
    {
        abort_unless($payoutRequest->status === PayoutRequest::STATUS_APPROVED, 422, 'Only approved requests can be marked paid.');

        $validated = $request->validate(['payment_reference' => ['nullable', 'string', 'max:100']]);
        $before = ['status' => $payoutRequest->status];

        $payoutRequest->update([
            'status'            => PayoutRequest::STATUS_PAID,
            'payment_reference' => $validated['payment_reference'] ?? null,
            'paid_at'           => now(),
        ]);

        $commissionService->debitForPayout($payoutRequest);

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
}
