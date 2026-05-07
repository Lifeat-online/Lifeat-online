<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CivicFaultReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CivicFaultReportController extends Controller
{
    public function index(Request $request): View
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

        return view('admin.fault-reports.index', [
            'reports' => $query->paginate(20)->withQueryString(),
            'categories' => CivicFaultReport::categories(),
            'statuses' => CivicFaultReport::statuses(),
        ]);
    }

    public function show(CivicFaultReport $faultReport): View
    {
        return view('admin.fault-reports.show', [
            'report' => $faultReport->load(['photos', 'reporter', 'assignedCouncillor']),
            'categories' => CivicFaultReport::categories(),
            'statuses' => CivicFaultReport::statuses(),
            'severities' => CivicFaultReport::severities(),
        ]);
    }

    public function moderate(Request $request, CivicFaultReport $faultReport): RedirectResponse
    {
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

        return redirect()->route('admin.fault-reports.show', $faultReport)->with('status', 'Moderation updated.');
    }
}

