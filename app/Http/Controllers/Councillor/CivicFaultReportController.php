<?php

namespace App\Http\Controllers\Councillor;

use App\Http\Controllers\Controller;
use App\Models\CivicFaultReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CivicFaultReportController extends Controller
{
    public function index(Request $request): View
    {
        $councillor = Auth::user()?->councillorProfile;
        abort_unless($councillor, 403);

        $query = CivicFaultReport::query()
            ->where('assigned_councillor_id', $councillor->id)
            ->with(['reporter', 'photos'])
            ->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return view('councillor.fault-reports.index', [
            'councillor' => $councillor,
            'reports' => $query->paginate(20)->withQueryString(),
            'statuses' => CivicFaultReport::statuses(),
            'categories' => CivicFaultReport::categories(),
        ]);
    }

    public function updateStatus(Request $request, CivicFaultReport $faultReport): RedirectResponse
    {
        $councillor = Auth::user()?->councillorProfile;
        abort_unless($councillor, 403);
        abort_unless($faultReport->assigned_councillor_id === $councillor->id, 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(CivicFaultReport::statuses()))],
        ]);

        $updates = ['status' => $validated['status']];
        if ($validated['status'] === CivicFaultReport::STATUS_ACKNOWLEDGED && ! $faultReport->acknowledged_at) {
            $updates['acknowledged_at'] = now();
        }
        if ($validated['status'] === CivicFaultReport::STATUS_IN_PROGRESS && ! $faultReport->in_progress_at) {
            $updates['in_progress_at'] = now();
        }
        if ($validated['status'] === CivicFaultReport::STATUS_RESOLVED && ! $faultReport->resolved_at) {
            $updates['resolved_at'] = now();
        }

        $faultReport->update($updates);

        return redirect()->route('councillor.faults.index')->with('status', 'Status updated.');
    }
}

