<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\PayoutRequest;
use App\Models\Article;
use App\Models\Event;
use App\Models\Listing;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PaymentRefund;
use App\Models\PushCampaign;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WriterApplication;
use App\Services\VapidKeySetupService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke(VapidKeySetupService $vapidKeys): View
    {
        $user = Auth::user();
        $supportThreshold = Carbon::now()->addDays(7);

        $isAdmin = $user->hasRole('super_admin');
        $canAccessDevDashboard = strtolower((string) $user->email) === 'jameskoen78@gmail.com';

        return view('admin.dashboard', [
            'dashboardRoleFlags' => [
                'isAdmin' => $isAdmin,
                'canAccessDevDashboard' => $canAccessDevDashboard,
                'canCreateContent' => $user->hasRole('admin', 'editor', 'staff'),
                'isSupport' => $user->hasRole('support'),
                'canReviewApplications' => $user->hasRole('admin', 'editor'),
            ],
            'counts' => [
                'users' => User::count(),
                'listings' => Listing::count(),
                'events' => Event::count(),
                'articles' => Article::count(),
                'writerApplications' => WriterApplication::count(),
            ],
            'supportCounts' => [
                'orders' => Order::count(),
                'payments' => Payment::count(),
                'subscriptions' => Subscription::count(),
                'notifications' => NotificationLog::count(),
                'refunds' => PaymentRefund::count(),
                'failedPayments' => Payment::where('status', 'failed')->count(),
                'pendingNotifications' => NotificationLog::whereIn('status', ['pending', 'queued', 'failed'])->count(),
                'expiringSubscriptions' => Subscription::where('status', 'active')
                    ->whereNotNull('ends_at')
                    ->whereBetween('ends_at', [Carbon::now(), $supportThreshold])
                    ->count(),
                'pushDeliveries' => NotificationLog::where('channel', 'push')->count(),
                'pendingPushCampaigns' => PushCampaign::whereNull('sent_at')
                    ->whereIn('status', ['active', 'scheduled'])
                    ->count(),
                'adCampaignsPendingApproval' => AdCampaign::where('status', 'ready')->count(),
                'adCampaignsActive' => AdCampaign::where('status', 'active')->count(),
                'pendingPayoutRequests' => PayoutRequest::whereIn('status', PayoutRequest::activeStatuses())->count(),
            ],
            'supportQueues' => [
                'failedPayments' => Payment::with(['order.user'])
                    ->where('status', 'failed')
                    ->latest()
                    ->limit(5)
                    ->get(),
                'pendingNotifications' => NotificationLog::query()
                    ->whereIn('status', ['pending', 'queued', 'failed'])
                    ->latest('sent_at')
                    ->limit(5)
                    ->get(),
                'expiringSubscriptions' => Subscription::with(['user', 'package'])
                    ->where('status', 'active')
                    ->whereNotNull('ends_at')
                    ->whereBetween('ends_at', [Carbon::now(), $supportThreshold])
                    ->orderBy('ends_at')
                    ->limit(5)
                    ->get(),
            ],
            'latestListings' => Listing::latest()->limit(5)->get(),
            'latestEvents' => Event::latest()->limit(5)->get(),
            'latestArticles' => Article::latest()->limit(5)->get(),
            'latestWriterApplications' => WriterApplication::latest('submitted_at')->limit(5)->get(),
            ...($canAccessDevDashboard ? $this->buildDevDashboardData($vapidKeys) : $this->emptyDevDashboardData()),
        ]);
    }

    private function buildDevDashboardData(VapidKeySetupService $vapidKeys): array
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->with(['permissions' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(function (Role $role): array {
                return [
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'users_count' => $role->users_count,
                    'permissions_count' => $role->permissions_count,
                    'permissions' => $role->permissions->pluck('name')->take(6)->values()->all(),
                    'extra_permissions_count' => max($role->permissions_count - 6, 0),
                ];
            });

        $permissions = Permission::query()
            ->withCount('roles')
            ->orderBy('name')
            ->get()
            ->map(function (Permission $permission): array {
                return [
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                    'roles_count' => $permission->roles_count,
                ];
            });

        $usersWithLinkedRoles = DB::table('role_user')->distinct()->count('user_id');

        $primaryRoleBreakdown = User::query()
            ->selectRaw('COALESCE(role, ?) as role_label, COUNT(*) as aggregate', ['unassigned'])
            ->groupBy('role')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->role_label,
                'count' => (int) $row->aggregate,
            ]);

        $databaseConnection = (string) config('database.default');
        $databaseConfig = config('database.connections.'.$databaseConnection, []);
        $diskFree = @disk_free_space(base_path());
        $diskTotal = @disk_total_space(base_path());

        return [
            'devSummaryCards' => [
                [
                    'label' => 'Roles',
                    'value' => (string) $roles->count(),
                    'note' => 'Configured access groups',
                ],
                [
                    'label' => 'Permissions',
                    'value' => (string) $permissions->count(),
                    'note' => 'Assignable capabilities',
                ],
                [
                    'label' => 'Users With Linked Roles',
                    'value' => (string) $usersWithLinkedRoles,
                    'note' => 'Users attached via role_user',
                ],
                [
                    'label' => 'Admin Accounts',
                    'value' => (string) $this->adminAccountCount(),
                    'note' => 'Legacy or linked admin access',
                ],
            ],
            'devWebPushStatus' => $vapidKeys->status(),
            'devRoleCards' => $roles,
            'devPermissionCards' => $permissions->sortByDesc('roles_count')->take(12)->values(),
            'devPrimaryRoleBreakdown' => $primaryRoleBreakdown,
            'devServerStatSections' => [
                [
                    'title' => 'Application',
                    'stats' => [
                        ['label' => 'Environment', 'value' => app()->environment()],
                        ['label' => 'Debug', 'value' => config('app.debug') ? 'Enabled' : 'Disabled'],
                        ['label' => 'Laravel', 'value' => app()->version()],
                        ['label' => 'PHP', 'value' => PHP_VERSION],
                        ['label' => 'Timezone', 'value' => (string) config('app.timezone')],
                        ['label' => 'App URL', 'value' => (string) config('app.url')],
                    ],
                ],
                [
                    'title' => 'Runtime',
                    'stats' => [
                        ['label' => 'Host', 'value' => request()->getHost()],
                        ['label' => 'Server Software', 'value' => (string) (request()->server('SERVER_SOFTWARE') ?: 'Unknown')],
                        ['label' => 'PHP SAPI', 'value' => PHP_SAPI],
                        ['label' => 'OS Family', 'value' => PHP_OS_FAMILY],
                        ['label' => 'Queue Connection', 'value' => (string) config('queue.default')],
                        ['label' => 'Cache Store', 'value' => (string) config('cache.default')],
                        ['label' => 'Session Driver', 'value' => (string) config('session.driver')],
                    ],
                ],
                [
                    'title' => 'Storage And Database',
                    'stats' => [
                        ['label' => 'DB Connection', 'value' => $databaseConnection !== '' ? $databaseConnection : 'Unknown'],
                        ['label' => 'Database', 'value' => (string) ($databaseConfig['database'] ?? 'Unknown')],
                        ['label' => 'Migrations Run', 'value' => $this->migrationCount()],
                        ['label' => 'Latest Batch', 'value' => $this->latestMigrationBatch()],
                        ['label' => 'Disk Free', 'value' => $this->formatBytes($diskFree)],
                        ['label' => 'Disk Total', 'value' => $this->formatBytes($diskTotal)],
                        ['label' => 'Storage Writable', 'value' => is_writable(storage_path()) ? 'Yes' : 'No'],
                    ],
                ],
            ],
        ];
    }

    private function emptyDevDashboardData(): array
    {
        return [
            'devSummaryCards' => [],
            'devWebPushStatus' => [
                'configured' => false,
                'public_key_configured' => false,
                'private_key_configured' => false,
                'subject' => '',
                'storage_ready' => false,
            ],
            'devRoleCards' => collect(),
            'devPermissionCards' => collect(),
            'devPrimaryRoleBreakdown' => collect(),
            'devServerStatSections' => [],
        ];
    }

    private function adminAccountCount(): int
    {
        return User::whereHas('roles', fn ($query) => $query->whereIn('slug', ['super_admin']))->count();
    }

    private function migrationCount(): string
    {
        if (! Schema::hasTable('migrations')) {
            return 'Unavailable';
        }

        return (string) DB::table('migrations')->count();
    }

    private function latestMigrationBatch(): string
    {
        if (! Schema::hasTable('migrations')) {
            return 'Unavailable';
        }

        $batch = DB::table('migrations')->max('batch');

        return $batch === null ? 'Unavailable' : (string) $batch;
    }

    private function formatBytes(float|int|false $bytes): string
    {
        if (! is_numeric($bytes) || $bytes < 0) {
            return 'Unavailable';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit === 0 ? 0 : 2).' '.$units[$unit];
    }
}
