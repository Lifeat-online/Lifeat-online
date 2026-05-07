<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Councillor;
use App\Models\CivicFaultReport;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CivicFaultReportController extends Controller
{
    public function index(Request $request)
    {
        $query = CivicFaultReport::query()
            ->with(['reporter', 'assignedCouncillor'])
            ->latest();

        if ($request->filled('approval')) {
            $query->where('is_approved', $request->string('approval')->toString() === 'approved');
        }

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if (($councillor = $request->string('councillor')->toString()) !== '') {
            if ($councillor === 'unassigned') {
                $query->whereNull('assigned_councillor_id');
            } else {
                $query->where('assigned_councillor_id', (int) $councillor);
            }
        }

        if (($q = trim((string) $request->string('q'))) !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('description', 'like', "%{$q}%")
                    ->orWhere('address_label', 'like', "%{$q}%")
                    ->orWhereHas('reporter', function ($reporter) use ($q) {
                        $reporter->where('email', 'like', "%{$q}%")
                            ->orWhere('name', 'like', "%{$q}%");
                    });
            });
        }

        $from = (string) $request->string('from');
        $to = (string) $request->string('to');
        if ($from !== '') {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }
        if ($to !== '') {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        $sort = $request->string('sort', 'newest')->toString();
        if ($sort === 'oldest') {
            $query->oldest();
        } else {
            $query->latest();
        }

        $reports = $query->paginate(20)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'reports' => $reports,
                'meta' => [
                    'categories' => CivicFaultReport::categories(),
                    'statuses' => CivicFaultReport::statuses(),
                ],
            ]);
        }

        return view('admin.fault-reports.index', [
            'reports' => $reports,
            'categories' => CivicFaultReport::categories(),
            'statuses' => CivicFaultReport::statuses(),
            'councillors' => Councillor::query()->where('is_active', true)->orderBy('full_name')->get(),
            'filters' => [
                'approval' => $request->string('approval')->toString(),
                'category' => $request->string('category')->toString(),
                'status' => $request->string('status')->toString(),
                'councillor' => $request->string('councillor')->toString(),
                'q' => trim((string) $request->string('q')),
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
            ],
        ]);
    }

    public function show(Request $request, CivicFaultReport $faultReport)
    {
        $faultReport->load(['photos', 'reporter', 'assignedCouncillor']);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'report' => $faultReport,
                'meta' => [
                    'categories' => CivicFaultReport::categories(),
                    'statuses' => CivicFaultReport::statuses(),
                    'severities' => CivicFaultReport::severities(),
                ],
            ]);
        }

        return view('admin.fault-reports.show', [
            'report' => $faultReport,
            'categories' => CivicFaultReport::categories(),
            'statuses' => CivicFaultReport::statuses(),
            'severities' => CivicFaultReport::severities(),
            'councillors' => Councillor::query()->where('is_active', true)->orderBy('full_name')->get(),
        ]);
    }

    public function moderate(Request $request, CivicFaultReport $faultReport, AuditLogService $audit)
    {
        $before = $faultReport->toArray();
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['decision'] === 'approve') {
            $faultReport->update([
                'is_approved' => true,
                'moderated_by_user_id' => $request->user()?->id,
                'moderated_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
        } else {
            $faultReport->update([
                'is_approved' => false,
                'moderated_by_user_id' => $request->user()?->id,
                'moderated_at' => now(),
                'rejected_at' => now(),
                'rejection_reason' => $validated['rejection_reason'] ?: 'Rejected by moderator.',
            ]);
        }

        $audit->log($request, 'civic_fault_report.moderated', $faultReport, $before, $faultReport->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'report' => $faultReport->fresh()]);
        }

        return redirect()->route('admin.fault-reports.show', $faultReport)->with('status', 'Moderation updated.');
    }

    public function update(Request $request, CivicFaultReport $faultReport, AuditLogService $audit)
    {
        $before = $faultReport->toArray();

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(CivicFaultReport::statuses()))],
            'assigned_councillor_id' => ['nullable', 'integer', 'exists:councillors,id'],
        ]);

        $timestamps = [];
        $status = $validated['status'];
        if ($status === CivicFaultReport::STATUS_ACKNOWLEDGED && ! $faultReport->acknowledged_at) {
            $timestamps['acknowledged_at'] = now();
        }
        if ($status === CivicFaultReport::STATUS_IN_PROGRESS && ! $faultReport->in_progress_at) {
            $timestamps['in_progress_at'] = now();
        }
        if ($status === CivicFaultReport::STATUS_RESOLVED && ! $faultReport->resolved_at) {
            $timestamps['resolved_at'] = now();
        }

        $faultReport->update([
            'status' => $status,
            'assigned_councillor_id' => $validated['assigned_councillor_id'] ?: null,
        ] + $timestamps);

        $audit->log($request, 'civic_fault_report.updated', $faultReport, $before, $faultReport->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'report' => $faultReport->fresh()]);
        }

        return redirect()->route('admin.fault-reports.show', $faultReport)->with('status', 'Report updated.');
    }

    public function bulk(Request $request, AuditLogService $audit)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:civic_fault_reports,id'],
            'action' => ['required', Rule::in(['approve', 'reject', 'assign', 'set_status'])],
            'assigned_councillor_id' => ['nullable', 'integer', 'exists:councillors,id'],
            'status' => ['nullable', Rule::in(array_keys(CivicFaultReport::statuses()))],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $reports = CivicFaultReport::query()->whereIn('id', $validated['ids'])->get();

        foreach ($reports as $report) {
            $before = $report->toArray();

            if ($validated['action'] === 'approve') {
                $report->update([
                    'is_approved' => true,
                    'moderated_by_user_id' => $request->user()?->id,
                    'moderated_at' => now(),
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ]);
                $audit->log($request, 'civic_fault_report.bulk_approved', $report, $before, $report->fresh()->toArray());
                continue;
            }

            if ($validated['action'] === 'reject') {
                $report->update([
                    'is_approved' => false,
                    'moderated_by_user_id' => $request->user()?->id,
                    'moderated_at' => now(),
                    'rejected_at' => now(),
                    'rejection_reason' => $validated['rejection_reason'] ?: 'Rejected by moderator.',
                ]);
                $audit->log($request, 'civic_fault_report.bulk_rejected', $report, $before, $report->fresh()->toArray());
                continue;
            }

            if ($validated['action'] === 'assign') {
                $report->update([
                    'assigned_councillor_id' => $validated['assigned_councillor_id'] ?: null,
                ]);
                $audit->log($request, 'civic_fault_report.bulk_assigned', $report, $before, $report->fresh()->toArray());
                continue;
            }

            if ($validated['action'] === 'set_status') {
                $status = (string) ($validated['status'] ?? '');
                if ($status === '') {
                    continue;
                }

                $timestamps = [];
                if ($status === CivicFaultReport::STATUS_ACKNOWLEDGED && ! $report->acknowledged_at) {
                    $timestamps['acknowledged_at'] = now();
                }
                if ($status === CivicFaultReport::STATUS_IN_PROGRESS && ! $report->in_progress_at) {
                    $timestamps['in_progress_at'] = now();
                }
                if ($status === CivicFaultReport::STATUS_RESOLVED && ! $report->resolved_at) {
                    $timestamps['resolved_at'] = now();
                }

                $report->update(['status' => $status] + $timestamps);
                $audit->log($request, 'civic_fault_report.bulk_status_updated', $report, $before, $report->fresh()->toArray());
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'affected' => $reports->count()]);
        }

        return redirect()->route('admin.fault-reports.index')->with('status', 'Bulk operation completed.');
    }
}
