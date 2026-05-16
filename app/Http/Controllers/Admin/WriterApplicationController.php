<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WriterApplication;
use App\Notifications\WriterApplicationApprovedNotification;
use App\Services\WriterApplicationOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WriterApplicationController extends Controller
{
    private const RECENTLY_CONTACTED_DAYS = 7;

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $contact = $request->string('contact')->toString();
        $recentThreshold = now()->subDays(self::RECENTLY_CONTACTED_DAYS);

        $applications = WriterApplication::query()
            ->with('user')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($contact === 'needs_contact', fn ($query) => $query
                ->where('status', WriterApplication::STATUS_APPROVED)
                ->whereNull('access_notified_at'))
            ->when($contact === 'recently_contacted', fn ($query) => $query
                ->whereNotNull('access_notified_at')
                ->where('access_notified_at', '>=', $recentThreshold))
            ->orderByRaw("case when status = 'pending' then 0 when status = 'under_review' then 1 else 2 end")
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $accessAuditLogs = AuditLog::query()
            ->where('subject_type', WriterApplication::class)
            ->whereIn('subject_id', $applications->getCollection()->pluck('id'))
            ->whereIn('action', ['writer_application.reviewed', 'writer_application.access_resent'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('subject_id');

        $applications->setCollection(
            $applications->getCollection()->map(function (WriterApplication $application) use ($accessAuditLogs) {
                $logs = ($accessAuditLogs->get($application->id) ?? collect())
                    ->filter(function (AuditLog $log) {
                        return match ($log->action) {
                            'writer_application.reviewed' => ! empty($log->after_json['access_notified_at'] ?? null),
                            'writer_application.access_resent' => true,
                            default => false,
                        };
                    })
                    ->values();

                $application->access_summary = [
                    'last_sent_at' => $application->access_notified_at,
                    'resend_available_at' => $this->accessResendAvailableAt($application),
                    'event_count' => $logs->count(),
                    'last_event_action' => match ($logs->first()?->action) {
                        'writer_application.access_resent' => 'resent',
                        'writer_application.reviewed' => 'sent',
                        default => null,
                    },
                ];

                return $application;
            })
        );

        return view('admin.writer-applications.index', [
            'applications' => $applications,
            'selectedStatus' => $status,
            'selectedContact' => $contact,
            'statusOptions' => WriterApplication::reviewStatuses(),
            'statusCounts' => WriterApplication::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'contactCounts' => [
                'needs_contact' => WriterApplication::query()
                    ->where('status', WriterApplication::STATUS_APPROVED)
                    ->whereNull('access_notified_at')
                    ->count(),
                'recently_contacted' => WriterApplication::query()
                    ->whereNotNull('access_notified_at')
                    ->where('access_notified_at', '>=', $recentThreshold)
                    ->count(),
            ],
        ]);
    }

    public function show(WriterApplication $writerApplication): View
    {
        return view('admin.writer-applications.show', [
            'application' => $writerApplication->load('user'),
            'decisionOptions' => WriterApplication::decisionStatuses(),
            'onboardingRoleOptions' => WriterApplication::onboardingRoles(),
            'resendAvailableAt' => $this->accessResendAvailableAt($writerApplication),
            'accessHistory' => $this->accessHistory($writerApplication),
        ]);
    }

    public function document(WriterApplication $writerApplication, string $document): StreamedResponse
    {
        $path = match ($document) {
            'id' => $writerApplication->id_document_path,
            'banking' => $writerApplication->banking_document_path,
            'residence' => $writerApplication->proof_of_residence_path,
            default => null,
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function review(Request $request, WriterApplication $writerApplication): RedirectResponse
    {
        $before = [
            'status' => $writerApplication->status,
            'assigned_role' => $writerApplication->assigned_role,
            'user_id' => $writerApplication->user_id,
            'reviewed_at' => optional($writerApplication->reviewed_at)?->toDateTimeString(),
            'onboarded_at' => optional($writerApplication->onboarded_at)?->toDateTimeString(),
            'access_notified_at' => optional($writerApplication->access_notified_at)?->toDateTimeString(),
            'admin_notes' => $writerApplication->admin_notes,
        ];

        $data = $request->validate([
            'status' => ['required', Rule::in(WriterApplication::decisionStatuses())],
            'assigned_role' => [
                Rule::requiredIf(fn () => $request->input('status') === WriterApplication::STATUS_APPROVED),
                'nullable',
                Rule::in(WriterApplication::onboardingRoles()),
            ],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($writerApplication->onboarded_at && $data['status'] !== WriterApplication::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Onboarded applications must remain approved. Update notes instead of downgrading the status.',
            ]);
        }

        $shouldNotifyAccess = $this->shouldNotifyAccess($writerApplication, $data['status']);

        $writerApplication->fill([
            'status' => $data['status'],
            'assigned_role' => $data['status'] === WriterApplication::STATUS_APPROVED ? $data['assigned_role'] : null,
            'admin_notes' => $data['admin_notes'] ?? null,
            'reviewed_at' => now(),
        ]);

        $accessNotificationSent = false;

        if ($data['status'] === WriterApplication::STATUS_APPROVED) {
            $user = app(WriterApplicationOnboardingService::class)->onboard($writerApplication, $data['assigned_role']);
            $writerApplication->forceFill([
                'user_id' => $user->id,
                'onboarded_at' => $writerApplication->onboarded_at ?: now(),
            ]);

            if ($shouldNotifyAccess) {
                $this->sendAccessNotification($writerApplication, $user);
                $accessNotificationSent = true;
            }
        } elseif (! $writerApplication->onboarded_at) {
            $writerApplication->forceFill([
                'onboarded_at' => null,
                'access_notified_at' => null,
            ]);
        }

        $writerApplication->save();

        $this->logAudit($request, 'writer_application.reviewed', $writerApplication, $before, [
            'status' => $writerApplication->status,
            'assigned_role' => $writerApplication->assigned_role,
            'user_id' => $writerApplication->user_id,
            'reviewed_at' => optional($writerApplication->reviewed_at)?->toDateTimeString(),
            'onboarded_at' => optional($writerApplication->onboarded_at)?->toDateTimeString(),
            'access_notified_at' => optional($writerApplication->access_notified_at)?->toDateTimeString(),
            'admin_notes' => $writerApplication->admin_notes,
        ]);

        return redirect()
            ->route('admin.writer-applications.show', $writerApplication)
            ->with('status', $accessNotificationSent ? 'Application review saved and access email sent.' : 'Application review saved.');
    }

    public function resendAccess(Request $request, WriterApplication $writerApplication): RedirectResponse
    {
        if ($writerApplication->status !== WriterApplication::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Only approved applications can receive access emails.',
            ]);
        }

        $user = $writerApplication->user;

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => 'This approved application does not have a linked platform account yet.',
            ]);
        }

        $before = [
            'user_id' => $writerApplication->user_id,
            'access_notified_at' => optional($writerApplication->access_notified_at)?->toDateTimeString(),
        ];

        $this->ensureAccessResendAllowed($writerApplication);
        $this->sendAccessNotification($writerApplication, $user);
        $writerApplication->save();

        $this->logAudit($request, 'writer_application.access_resent', $writerApplication, $before, [
            'user_id' => $writerApplication->user_id,
            'access_notified_at' => optional($writerApplication->access_notified_at)?->toDateTimeString(),
        ]);

        return redirect()
            ->route('admin.writer-applications.show', $writerApplication)
            ->with('status', 'Access email sent again.');
    }

    private function logAudit(Request $request, string $action, WriterApplication $subject, array $before, array $after): void
    {
        AuditLog::create([
            'actor_user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function sendAccessNotification(WriterApplication $writerApplication, $user): void
    {
        $token = Password::broker()->createToken($user);
        $user->notify(new WriterApplicationApprovedNotification($writerApplication, $token));
        $writerApplication->forceFill([
            'access_notified_at' => now(),
        ]);
    }

    private function accessResendAvailableAt(WriterApplication $writerApplication, int $cooldownMinutes = 5): ?Carbon
    {
        if (! $writerApplication->access_notified_at) {
            return null;
        }

        $availableAt = $writerApplication->access_notified_at->copy()->addMinutes($cooldownMinutes);

        return $availableAt->isFuture() ? $availableAt : null;
    }

    private function ensureAccessResendAllowed(WriterApplication $writerApplication, int $cooldownMinutes = 5): void
    {
        $availableAt = $this->accessResendAvailableAt($writerApplication, $cooldownMinutes);

        if ($availableAt) {
            throw ValidationException::withMessages([
                'access_notified_at' => 'Access email was sent recently. Try again after '.$availableAt->format('H:i').'.',
            ]);
        }
    }

    private function accessHistory(WriterApplication $writerApplication)
    {
        return AuditLog::query()
            ->with('actor')
            ->where('subject_type', $writerApplication::class)
            ->where('subject_id', $writerApplication->getKey())
            ->whereIn('action', ['writer_application.reviewed', 'writer_application.access_resent'])
            ->latest()
            ->limit(10)
            ->get()
            ->filter(function (AuditLog $log) {
                return match ($log->action) {
                    'writer_application.reviewed' => ! empty($log->after_json['access_notified_at'] ?? null),
                    'writer_application.access_resent' => true,
                    default => false,
                };
            })
            ->map(function (AuditLog $log) {
                $action = $log->action === 'writer_application.access_resent'
                    ? 'Access email resent'
                    : 'Access email sent';

                return [
                    'action' => $action,
                    'actor' => $log->actor?->name ?? 'System',
                    'occurred_at' => $log->created_at,
                    'detail' => match ($log->action) {
                        'writer_application.access_resent' => 'Manual resend triggered from the review screen.',
                        default => 'Approval flow sent the first access email.',
                    },
                ];
            })
            ->values();
    }

    private function shouldNotifyAccess(WriterApplication $writerApplication, string $newStatus): bool
    {
        return $newStatus === WriterApplication::STATUS_APPROVED
            && ($writerApplication->status !== WriterApplication::STATUS_APPROVED || ! $writerApplication->access_notified_at);
    }
}
