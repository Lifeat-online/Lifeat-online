<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminActionStationService;
use App\Services\ActionStationContentReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActionStationController extends Controller
{
    public function index(Request $request, AdminActionStationService $station): View
    {
        return view('admin.action-station.index', $station->dashboard($request->user()) + [
            'selectedGroup' => $request->string('group')->toString() ?: 'all',
        ]);
    }

    public function updateSettings(Request $request, ActionStationContentReviewService $reviews): RedirectResponse
    {
        $validated = $request->validate([
            'auto_publish' => ['nullable', 'boolean'],
            'approval_threshold' => ['required', 'integer', 'min:50', 'max:100'],
            'batch_limit' => ['required', 'integer', 'min:1', 'max:25'],
        ]);

        $reviews->updateSettings($request->user(), [
            'auto_publish' => $request->boolean('auto_publish'),
            'approval_threshold' => $validated['approval_threshold'],
            'batch_limit' => $validated['batch_limit'],
        ]);

        return redirect()->route('admin.action-station.index')
            ->with('status', 'Action Station AI review settings saved.');
    }

    public function reviewContent(Request $request, ActionStationContentReviewService $reviews): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['listing', 'event', 'voucher', 'ad_campaign'])],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $result = $reviews->reviewByReference($validated['type'], (int) $validated['id'], $request->user());

        if (! ($result['ok'] ?? false)) {
            return redirect()->route('admin.action-station.index')
                ->withErrors(['review' => $result['message'] ?? 'AI review failed.']);
        }

        return redirect()->route('admin.action-station.index')
            ->with('status', $result['message'] ?? 'AI review completed.');
    }

    public function reviewContentQueue(Request $request, ActionStationContentReviewService $reviews): RedirectResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $result = $reviews->reviewQueue($request->user(), $validated['limit'] ?? null);

        return redirect()->route('admin.action-station.index')
            ->with('status', "AI reviewed {$result['count']} item(s): {$result['approved']} approved, {$result['auto_published']} auto-published, {$result['human_review']} routed to humans.");
    }
}
