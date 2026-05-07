<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $action = trim((string) $request->string('action'));
        $actorId = $request->integer('actor_user_id');
        $subjectType = trim((string) $request->string('subject_type'));
        $subjectId = trim((string) $request->string('subject_id'));
        $from = (string) $request->string('from');
        $to = (string) $request->string('to');
        $sort = $request->string('sort')->toString() ?: 'newest';

        $query = AuditLog::query()
            ->with('actor')
            ->when($action !== '', fn ($q) => $q->where('action', 'like', '%'.mb_substr($action, 0, 120).'%'))
            ->when($actorId > 0, fn ($q) => $q->where('actor_user_id', $actorId))
            ->when($subjectType !== '', fn ($q) => $q->where('subject_type', 'like', '%'.mb_substr($subjectType, 0, 180).'%'))
            ->when($subjectId !== '', fn ($q) => $q->where('subject_id', $subjectId))
            ->when($from !== '', fn ($q) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($to !== '', fn ($q) => $q->where('created_at', '<=', $to.' 23:59:59'));

        $query->orderBy('created_at', $sort === 'oldest' ? 'asc' : 'desc');

        $logs = $query->paginate(30)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'audit_logs' => $logs]);
        }

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => [
                'action' => $action,
                'actor_user_id' => $actorId ?: null,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
            ],
        ]);
    }
}
