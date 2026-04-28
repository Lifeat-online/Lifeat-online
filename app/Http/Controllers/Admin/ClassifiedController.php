<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassifiedController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: Classified::STATUS_PENDING;

        return view('admin.classifieds.index', [
            'classifieds' => Classified::with(['user', 'reviewer'])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['status' => $status],
        ]);
    }

    public function show(Classified $classified): View
    {
        return view('admin.classifieds.show', [
            'classified' => $classified->load(['user', 'reviewer']),
            'decisionOptions' => Classified::reviewableStatuses(),
        ]);
    }

    public function review(Request $request, Classified $classified): RedirectResponse
    {
        $before = [
            'status' => $classified->status,
            'reviewed_by_user_id' => $classified->reviewed_by_user_id,
            'reviewed_at' => optional($classified->reviewed_at)?->toDateTimeString(),
            'moderation_notes' => $classified->moderation_notes,
            'published_at' => optional($classified->published_at)?->toDateTimeString(),
        ];

        $data = $request->validate([
            'status' => ['required', Rule::in(Classified::reviewableStatuses())],
            'moderation_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $classified->fill([
            'status' => $data['status'],
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
            'moderation_notes' => $data['moderation_notes'] ?? null,
            'published_at' => $data['status'] === Classified::STATUS_PUBLISHED ? now() : null,
        ]);
        $classified->save();

        $this->logAudit($request, $classified, $before, [
            'status' => $classified->status,
            'reviewed_by_user_id' => $classified->reviewed_by_user_id,
            'reviewed_at' => optional($classified->reviewed_at)?->toDateTimeString(),
            'moderation_notes' => $classified->moderation_notes,
            'published_at' => optional($classified->published_at)?->toDateTimeString(),
        ]);

        return redirect()
            ->route('admin.classifieds.show', $classified)
            ->with('status', 'Classified moderation decision saved.');
    }

    private function logAudit(Request $request, Classified $classified, array $before, array $after): void
    {
        AuditLog::create([
            'actor_user_id' => $request->user()?->id,
            'action' => 'classified.reviewed',
            'subject_type' => Classified::class,
            'subject_id' => $classified->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
