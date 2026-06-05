<?php

namespace App\Support\Caching;

use App\Models\Article;
use App\Models\Category;
use App\Models\Event;
use App\Models\Listing;
use App\Models\LocationNode;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Tag;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PublicReadCache
{
    private const PREFIX = 'lifeat:public-read';

    public static function settingValue(string $key, mixed $default = null): mixed
    {
        $settings = self::settings();
        $value = $settings[$key] ?? null;

        return $value ?? $default;
    }

    public static function settings(): array
    {
        return self::remember('settings', 'all', 'settings_ttl', function (): array {
            return Setting::query()
                ->orderBy('key')
                ->pluck('value', 'key')
                ->all();
        });
    }

    public static function pricing(array $keysWithDefaults): array
    {
        return collect($keysWithDefaults)
            ->mapWithKeys(fn (mixed $default, string $key): array => [$key => (float) self::settingValue($key, $default)])
            ->all();
    }

    public static function activePackagesForType(string $typeSlug, string $sort = 'name'): Collection
    {
        $packages = collect(self::remember('catalog', 'active-packages:'.$typeSlug, 'catalog_ttl', function () use ($typeSlug): array {
            return Package::query()
                ->with(['type', 'prices'])
                ->active()
                ->whereHas('type', fn ($query) => $query->where('slug', $typeSlug))
                ->orderBy('name')
                ->get()
                ->map(fn (Package $package): array => self::packagePayload($package))
                ->all();
        }));

        return match ($sort) {
            'self_service' => $packages
                ->sortBy(fn (array $package): string => sprintf('%d-%s', $package['is_self_service'] ? 1 : 0, $package['name']))
                ->values(),
            default => $packages->sortBy('name')->values(),
        };
    }

    public static function listingCategories(): Collection
    {
        return collect(self::remember('public', 'categories:listing:'.app()->getLocale(), 'public_ttl', function (): array {
            return Category::query()
                ->where('type', 'listing')
                ->with('contentTranslations')
                ->withCount([
                    'listings as visible_listings_count' => fn ($query) => $query->published(),
                ])
                ->orderByDesc('visible_listings_count')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => self::categoryPayload($category, 'visible_listings_count'))
                ->all();
        }));
    }

    public static function eventCategories(): Collection
    {
        return collect(self::remember('public', 'categories:event:'.app()->getLocale(), 'public_ttl', function (): array {
            return Category::query()
                ->where('type', 'event')
                ->with('contentTranslations')
                ->withCount([
                    'events as visible_events_count' => fn ($query) => $query->published(),
                ])
                ->orderByDesc('visible_events_count')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => self::categoryPayload($category, 'visible_events_count'))
                ->all();
        }));
    }

    public static function articleCategories(): Collection
    {
        return collect(self::remember('public', 'categories:article:'.app()->getLocale(), 'public_ttl', function (): array {
            return Category::query()
                ->where('type', 'article')
                ->with('contentTranslations')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => self::categoryPayload($category))
                ->all();
        }));
    }

    public static function searchCategories(): Collection
    {
        return collect(self::remember('public', 'categories:search:'.app()->getLocale(), 'public_ttl', function (): array {
            return Category::query()
                ->whereIn('type', ['listing', 'event', 'article'])
                ->with('contentTranslations')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => self::categoryPayload($category))
                ->all();
        }));
    }

    public static function articleTags(): Collection
    {
        return collect(self::remember('public', 'tags:article:'.app()->getLocale(), 'public_ttl', function (): array {
            return Tag::query()
                ->where('type', 'article')
                ->with('contentTranslations')
                ->orderBy('name')
                ->get()
                ->map(fn (Tag $tag): array => self::referencePayload($tag))
                ->all();
        }));
    }

    public static function articleLocations(): Collection
    {
        return collect(self::remember('public', 'locations:article:'.app()->getLocale(), 'public_ttl', function (): array {
            return LocationNode::query()
                ->with('contentTranslations')
                ->orderBy('name')
                ->get()
                ->map(fn (LocationNode $location): array => self::referencePayload($location))
                ->all();
        }));
    }

    public static function popularListingLocations(int $limit = 6): Collection
    {
        return collect(self::remember('public', 'locations:popular-listings:'.$limit, 'public_ttl', function () use ($limit): array {
            return Listing::published()
                ->selectRaw('city, count(*) as listings_count')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->groupBy('city')
                ->orderByDesc('listings_count')
                ->orderBy('city')
                ->limit($limit)
                ->get()
                ->map(fn ($location): array => [
                    'city' => (string) $location->city,
                    'listings_count' => (int) $location->listings_count,
                ])
                ->all();
        }));
    }

    public static function popularEventLocations(int $limit = 6): Collection
    {
        return collect(self::remember('public', 'locations:popular-events:'.$limit, 'public_ttl', function () use ($limit): array {
            return Event::published()
                ->selectRaw('city, count(*) as events_count')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->groupBy('city')
                ->orderByDesc('events_count')
                ->orderBy('city')
                ->limit($limit)
                ->get()
                ->map(fn ($location): array => [
                    'city' => (string) $location->city,
                    'events_count' => (int) $location->events_count,
                ])
                ->all();
        }));
    }

    public static function publicStats(): array
    {
        return self::remember('public', 'stats', 'public_ttl', fn (): array => [
            'visible_listings' => Listing::published()->count(),
            'featured_listings' => Listing::published()->where('is_featured', true)->count(),
            'visible_events' => Event::published()->count(),
            'upcoming_events' => Event::published()->where('start_at', '>=', now()->startOfDay())->count(),
            'published_articles' => Article::published()->count(),
        ]);
    }

    public static function adminSupportCounts(int $expiringThresholdDays = 7): array
    {
        return self::remember('admin', 'support-counts', 'public_ttl', function () use ($expiringThresholdDays): array {
            $now = now();
            $threshold = $now->copy()->addDays($expiringThresholdDays);

            return [
                'orders' => \App\Models\Order::count(),
                'payments' => \App\Models\Payment::count(),
                'subscriptions' => \App\Models\Subscription::count(),
                'notifications' => \App\Models\NotificationLog::count(),
                'refunds' => \App\Models\PaymentRefund::count(),
                'failedPayments' => \App\Models\Payment::where('status', 'failed')->count(),
                'pendingNotifications' => \App\Models\NotificationLog::whereIn('status', ['pending', 'queued', 'failed'])->count(),
                'expiringSubscriptions' => \App\Models\Subscription::where('status', 'active')
                    ->whereNotNull('ends_at')
                    ->whereBetween('ends_at', [$now, $threshold])
                    ->count(),
                'pushDeliveries' => \App\Models\NotificationLog::where('channel', 'push')->count(),
                'pendingPushCampaigns' => \App\Models\PushCampaign::whereNull('sent_at')
                    ->whereIn('status', ['active', 'scheduled'])
                    ->count(),
                'adCampaignsPendingApproval' => \App\Models\AdCampaign::where('status', 'ready')->count(),
                'adCampaignsActive' => \App\Models\AdCampaign::where('status', 'active')->count(),
                'pendingPayoutRequests' => \App\Models\PayoutRequest::whereIn('status', \App\Models\PayoutRequest::activeStatuses())->count(),
            ];
        });
    }

    public static function vendorDashboardStats(int $storeId, int $lowStockThreshold = 5): array
    {
        return self::remember("vendor-store-{$storeId}", "dashboard-stats-{$lowStockThreshold}", 'public_ttl', function () use ($storeId, $lowStockThreshold): array {
            $paidStatuses = ['paid', 'processing', 'shipped', 'completed'];

            $row = \DB::table('mall_orders')
                ->where('mall_store_id', $storeId)
                ->selectRaw('COUNT(*) AS total_orders, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS paid_orders, COALESCE(SUM(CASE WHEN status IN (?, ?, ?, ?) THEN vendor_amount ELSE 0 END), 0) AS total_revenue', [...$paidStatuses, ...$paidStatuses])
                ->first();

            $productStats = \DB::table('mall_products')
                ->where('mall_store_id', $storeId)
                ->selectRaw('COUNT(*) AS total_products, SUM(CASE WHEN manage_stock = 1 AND stock_qty <= ? THEN 1 ELSE 0 END) AS low_stock', [$lowStockThreshold])
                ->first();

            return [
                'total_orders' => (int) ($row->total_orders ?? 0),
                'paid_orders' => (int) ($row->paid_orders ?? 0),
                'total_revenue' => (float) ($row->total_revenue ?? 0),
                'total_products' => (int) ($productStats->total_products ?? 0),
                'low_stock' => (int) ($productStats->low_stock ?? 0),
            ];
        });
    }

    public static function flushSettings(): void
    {
        self::bump('settings');
    }

    public static function flushCatalog(): void
    {
        self::bump('catalog');
    }

    public static function flushPublic(): void
    {
        self::bump('public');
    }

    public static function flushAdmin(): void
    {
        self::bump('admin');
    }

    public static function flushVendor(int $storeId): void
    {
        self::bump("vendor-store-{$storeId}");
    }

    public static function flushAll(): void
    {
        self::flushSettings();
        self::flushCatalog();
        self::flushPublic();
        self::flushAdmin();
    }

    private static function packagePayload(Package $package): array
    {
        $price = $package->prices
            ->filter(fn ($price): bool => (! $price->effective_from || $price->effective_from->lte(now()))
                && (! $price->effective_to || $price->effective_to->gt(now())))
            ->sortByDesc(fn ($price): string => sprintf(
                '%s-%010d',
                optional($price->effective_from)->toISOString() ?: '',
                $price->id
            ))
            ->first();

        return [
            'id' => $package->id,
            'type_slug' => (string) $package->type?->slug,
            'type_name' => (string) $package->type?->name,
            'slug' => $package->slug,
            'name' => $package->name,
            'description' => $package->description,
            'billing_model' => $package->billing_model,
            'is_self_service' => (bool) $package->is_self_service,
            'duration_days' => (int) $package->duration_days,
            'settings' => $package->settings_json ?: [],
            'current_price' => $price ? [
                'id' => $price->id,
                'amount' => (float) $price->amount,
                'currency' => $price->currency,
                'vat_inclusive' => (bool) $price->vat_inclusive,
            ] : null,
        ];
    }

    private static function categoryPayload(Category $category, ?string $countKey = null): array
    {
        $payload = self::referencePayload($category) + [
            'type' => $category->type,
            'description' => (string) $category->localizedValue('description'),
        ];

        if ($countKey) {
            $payload[$countKey] = (int) $category->{$countKey};
        }

        return $payload;
    }

    private static function referencePayload(object $model): array
    {
        return [
            'id' => $model->id,
            'slug' => $model->slug,
            'name' => (string) $model->localizedValue('name'),
        ];
    }

    private static function remember(string $namespace, string $key, string $ttlKey, Closure $callback): mixed
    {
        $ttl = (int) config('lifeat_cache.'.$ttlKey, 0);

        if ($ttl <= 0) {
            return $callback();
        }

        try {
            return Cache::remember(self::key($namespace, $key), $ttl, $callback);
        } catch (Throwable) {
            return $callback();
        }
    }

    private static function key(string $namespace, string $key): string
    {
        return self::PREFIX.':'.$namespace.':v'.self::version($namespace).':'.$key;
    }

    private static function version(string $namespace): int
    {
        try {
            return (int) Cache::rememberForever(self::PREFIX.':version:'.$namespace, fn (): int => 1);
        } catch (Throwable) {
            return 1;
        }
    }

    private static function bump(string $namespace): void
    {
        $key = self::PREFIX.':version:'.$namespace;

        try {
            if (! Cache::has($key)) {
                Cache::forever($key, 1);
            }

            Cache::increment($key);
        } catch (Throwable) {
            //
        }
    }
}
