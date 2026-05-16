<?php

namespace App\Http\Controllers;

use App\Models\CivicFaultPhoto;
use App\Models\CivicFaultReport;
use App\Notifications\CivicFaultReportedNotification;
use App\Services\CouncillorAssignmentService;
use App\Support\Validation\UploadRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CivicFaultReportController extends Controller
{
    public function create()
    {
        return view('faults.report', [
            'categories' => CivicFaultReport::categories(),
            'severities' => CivicFaultReport::severities(),
        ]);
    }

    public function store(Request $request, CouncillorAssignmentService $assignmentService)
    {
        $clientUuid = trim((string) $request->input('client_uuid', ''));
        if ($clientUuid === '' || $clientUuid === 'undefined' || $clientUuid === 'null' || ! Str::isUuid($clientUuid)) {
            $request->merge(['client_uuid' => null]);
        }

        $validated = $request->validate([
            'client_uuid' => ['nullable', 'uuid'],
            'category' => ['required', Rule::in(array_keys(CivicFaultReport::categories()))],
            'severity' => ['required', Rule::in(array_keys(CivicFaultReport::severities()))],
            'description' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address_label' => ['nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => UploadRules::requiredPublicImage(),
            'consent' => ['accepted'],
        ]);

        $user = Auth::user();

        $assigned = $assignmentService->assign(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $validated['category'],
        );

        $report = CivicFaultReport::create([
            'client_uuid' => $validated['client_uuid'] ?? null,
            'reporter_user_id' => $user->id,
            'assigned_councillor_id' => $assigned?->id,
            'category' => $validated['category'],
            'severity' => $validated['severity'],
            'status' => CivicFaultReport::STATUS_REPORTED,
            'address_label' => $validated['address_label'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'description' => $validated['description'],
            'consented_at' => now(),
            'is_approved' => false,
        ]);

        $photos = $request->file('photos', []);
        foreach (array_values($photos) as $index => $photo) {
            $path = $photo->store('fault-reports/'.$report->id, 'public');

            CivicFaultPhoto::create([
                'civic_fault_report_id' => $report->id,
                'path' => $path,
                'original_name' => $photo->getClientOriginalName(),
                'sort_order' => $index,
            ]);
        }

        if ($assigned && $assigned->user) {
            $assigned->user->notify(new CivicFaultReportedNotification($report));
        } elseif ($assigned && $assigned->email) {
            Notification::route('mail', $assigned->email)->notify(new CivicFaultReportedNotification($report));
        }

        return redirect()
            ->route('faults.index')
            ->with('status', 'Thanks — your report has been submitted and is pending moderation before it appears on the public map.');
    }
}
