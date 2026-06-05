<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AiGeneration;
use App\Models\ArticleBrief;
use App\Models\ContentTranslation;
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
use App\Models\ResearchItem;
use App\Models\ResearchSource;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WriterApplication;
use App\Support\Caching\PublicReadCache;
use App\Services\AiGatewayService;
use App\Services\AiImageService;
use App\Services\OpenRouterTranslationService;
use App\Services\GoogleMapsService;
use App\Services\PlatformTranslationBatchService;
use App\Services\VapidKeySetupService;
use App\Services\VoiceGatewayService;
use App\Support\Monitoring\OperationalKpiReport;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke(VapidKeySetupService $vapidKeys, OpenRouterTranslationService $translations, PlatformTranslationBatchService $translationBatch, GoogleMapsService $maps, AiGatewayService $ai, AiImageService $images, VoiceGatewayService $voice, OperationalKpiReport $kpis): View
    {
        $user = Auth::user();
        $supportThreshold = Carbon::now()->addDays(7);

        $isAdmin = $user->hasRole('super_admin');
        $canAccessDevDashboard = $user->hasRole('dev', 'developer', 'super_admin');

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
            'supportCounts' => PublicReadCache::adminSupportCounts(),
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
            'operationalKpis' => $kpis->run(),
            'latestListings' => Listing::latest()->limit(5)->get(),
            'latestEvents' => Event::latest()->limit(5)->get(),
            'latestArticles' => Article::latest()->limit(5)->get(),
            'latestWriterApplications' => WriterApplication::latest('submitted_at')->limit(5)->get(),
            ...($canAccessDevDashboard ? $this->buildDevDashboardData($vapidKeys, $translations, $translationBatch, $maps, $ai, $images, $voice) : $this->emptyDevDashboardData()),
        ]);
    }

    private function buildDevDashboardData(VapidKeySetupService $vapidKeys, OpenRouterTranslationService $translations, PlatformTranslationBatchService $translationBatch, GoogleMapsService $maps, AiGatewayService $ai, AiImageService $images, VoiceGatewayService $voice): array
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
            'devAiStatus' => $ai->status(),
            'devAiImageStatus' => $images->status(),
            'devVoiceStatus' => $voice->status(),
            'devAiWriterStatus' => $this->aiWriterStatus(),
            'devTranslationStatus' => $this->translationStatus($translations, $translationBatch),
            'devMapStatus' => $this->mapStatus($maps),
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
            'devAiStatus' => [
                'provider' => 'openrouter',
                'provider_label' => 'OpenRouter',
                'model' => '',
                'configured' => false,
                'source' => 'Missing',
                'masked_key' => '',
                'providers' => [],
                'feature_routes' => [],
                'profiles' => [],
            ],
            'devAiImageStatus' => [
                'provider' => 'openrouter',
                'provider_label' => 'OpenRouter Images',
                'model' => '',
                'base_url' => '',
                'size' => '1024x1024',
                'configured' => false,
                'source' => 'Missing',
                'masked_key' => '',
                'providers' => [],
            ],
            'devVoiceStatus' => [
                'provider' => 'elevenlabs',
                'provider_label' => 'ElevenLabs',
                'voice_id' => '',
                'model' => '',
                'english_model' => 'eleven_flash_v2_5',
                'afrikaans_model' => 'eleven_v3',
                'base_url' => 'https://api.elevenlabs.io/v1',
                'output_format' => 'mp3_44100_128',
                'configured' => false,
                'source' => 'Missing',
                'masked_key' => '',
                'providers' => [],
            ],
            'devAiWriterStatus' => [
                'active_sources' => 0,
                'new_research_items' => 0,
                'briefed_research_items' => 0,
                'pending_review_briefs' => 0,
                'approved_briefs' => 0,
                'drafted_briefs' => 0,
                'drafts_missing_images' => 0,
                'failed_generations' => 0,
                'latest_generation' => null,
            ],
            'devTranslationStatus' => [
                'supported_locales' => collect(config('localization.supported'))->keys()->all(),
                'target_locales' => [],
                'article_translations' => 0,
                'published_articles_missing' => collect(),
                'provider' => 'google',
                'model' => (string) config('services.openrouter.model', 'google/gemma-4-31b-it:free'),
                'configured' => false,
                'source' => 'Missing',
                'masked_key' => '',
                'azure_configured' => false,
                'azure_region' => '',
                'azure_masked_key' => '',
                'google_configured' => false,
                'google_masked_key' => '',
                'openrouter_configured' => false,
                'openrouter_masked_key' => '',
                'openrouter_model' => (string) config('services.openrouter.model', 'google/gemma-4-31b-it:free'),
                'sections' => [],
            ],
            'devMapStatus' => [
                'configured' => false,
                'source' => 'Missing',
                'masked_key' => '',
            ],
            'devRoleCards' => collect(),
            'devPermissionCards' => collect(),
            'devPrimaryRoleBreakdown' => collect(),
            'devServerStatSections' => [],
        ];
    }

    private function translationStatus(OpenRouterTranslationService $translations, PlatformTranslationBatchService $translationBatch): array
    {
        $supportedLocales = collect(config('localization.supported'))->keys()->values();

        $publishedArticlesMissing = Article::query()
            ->published()
            ->with('contentTranslations')
            ->latest('published_at')
            ->limit(25)
            ->get()
            ->map(function (Article $article) use ($supportedLocales): Article {
                $translated = $article->contentTranslations->pluck('locale');
                $article->missing_translation_locales = $supportedLocales
                    ->reject(fn (string $locale): bool => $locale === $article->sourceLocale())
                    ->diff($translated)
                    ->values()
                    ->all();

                return $article;
            })
            ->filter(fn (Article $article): bool => $article->missing_translation_locales !== [])
            ->take(8)
            ->values();

        return [
            'supported_locales' => $supportedLocales->all(),
            'target_locales' => $supportedLocales->all(),
            'article_translations' => ContentTranslation::query()
                ->where('translatable_type', Article::class)
                ->whereIn('locale', $supportedLocales->all())
                ->count(),
            'published_articles_missing' => $publishedArticlesMissing,
            'provider' => $translations->provider(),
            'model' => $translations->model(),
            'configured' => $translations->configured(),
            'source' => $translations->apiKeySource(),
            'masked_key' => $translations->maskedApiKey(),
            'azure_configured' => $translations->azureConfigured(),
            'azure_region' => $translations->azureRegion(),
            'azure_masked_key' => $translations->azureMaskedApiKey(),
            'google_configured' => $translations->googleConfigured(),
            'google_masked_key' => $translations->googleMaskedApiKey(),
            'openrouter_configured' => $translations->openRouterConfigured(),
            'openrouter_masked_key' => $translations->openRouterMaskedApiKey(),
            'openrouter_model' => $translations->openRouterModel(),
            'sections' => $translationBatch->status(),
        ];
    }

    private function mapStatus(GoogleMapsService $maps): array
    {
        return [
            'configured' => $maps->configured(),
            'source' => $maps->apiKeySource(),
            'masked_key' => $maps->maskedApiKey(),
        ];
    }

    private function aiWriterStatus(): array
    {
        $latestGeneration = AiGeneration::query()
            ->whereIn('feature_key', ['editorial_brief', 'jimmy_article_draft', 'article_image'])
            ->latest()
            ->first();

        return [
            'active_sources' => ResearchSource::query()->active()->count(),
            'new_research_items' => ResearchItem::query()->where('status', ResearchItem::STATUS_NEW)->count(),
            'briefed_research_items' => ResearchItem::query()->where('status', ResearchItem::STATUS_BRIEFED)->count(),
            'pending_review_briefs' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_PENDING_REVIEW)->count(),
            'approved_briefs' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_APPROVED)->whereDoesntHave('article')->count(),
            'drafted_briefs' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_DRAFTED)->count(),
            'drafts_missing_images' => Article::query()
                ->whereNotNull('article_brief_id')
                ->whereNull('featured_image')
                ->whereIn('status', ['draft', 'pending_review', 'revision_requested'])
                ->count(),
            'failed_generations' => AiGeneration::query()
                ->whereIn('feature_key', ['editorial_brief', 'jimmy_article_draft', 'article_image'])
                ->where('status', AiGeneration::STATUS_FAILED)
                ->count(),
            'latest_generation' => $latestGeneration ? [
                'feature_key' => $latestGeneration->feature_key,
                'provider' => $latestGeneration->provider,
                'status' => $latestGeneration->status,
                'created_at' => optional($latestGeneration->created_at)->diffForHumans(),
            ] : null,
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
