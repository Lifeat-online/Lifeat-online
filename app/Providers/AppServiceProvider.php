<?php

namespace App\Providers;

use App\Ai\Contracts\EmbeddingProvider;
use App\Ai\Editorial\Contracts\HostResolver;
use App\Ai\Editorial\DnsHostResolver;
use App\Ai\Operator\AiOperatorTaskPlanner;
use App\Ai\Operator\Contracts\OperatorTaskPlanner;
use App\Ai\Operator\Contracts\WebSearchProvider;
use App\Ai\Operator\PerplexityWebSearchProvider;
use App\Ai\Providers\FakeEmbeddingProvider;
use App\Ai\Providers\OpenAiEmbeddingProvider;
use App\Events\MallOrderPaid;
use App\Events\PaymentPaid;
use App\Events\PayoutPaid;
use App\Events\PushCampaignDispatched;
use App\Events\SubscriptionActivated;
use App\Listeners\RecordRevenueLifecycleEvent;
use App\Listeners\SendMallOrderPaidEmails;
use App\Models\Article;
use App\Models\Category;
use App\Models\CivicFaultReport;
use App\Models\Classified;
use App\Models\Event as LifeEvent;
use App\Models\Listing;
use App\Models\LocationNode;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\StaffWallet;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\Voucher;
use App\Observers\QueueArticleKnowledge;
use App\Observers\QueueContentTranslations;
use App\Observers\QueuePublicKnowledge;
use App\Policies\ListingPolicy;
use App\Policies\NotificationLogPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PayoutRequestPolicy;
use App\Policies\StaffWalletPolicy;
use App\Policies\SubscriptionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OperatorTaskPlanner::class, AiOperatorTaskPlanner::class);
        $this->app->bind(WebSearchProvider::class, PerplexityWebSearchProvider::class);
        $this->app->bind(HostResolver::class, DnsHostResolver::class);
        $this->app->bind(EmbeddingProvider::class, function (): EmbeddingProvider {
            $dimensions = (int) config('ai_platform.embeddings.dimensions', 1536);

            if ($this->app->environment('testing') || config('ai_platform.embeddings.provider') === 'fake') {
                return new FakeEmbeddingProvider($dimensions);
            }

            return new OpenAiEmbeddingProvider(
                apiKey: (string) config('ai_platform.embeddings.key', ''),
                modelName: (string) config('ai_platform.embeddings.model', 'text-embedding-3-small'),
                vectorDimensions: $dimensions,
                baseUrl: (string) config('ai_platform.embeddings.base_url', 'https://api.openai.com/v1'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Listing::class, ListingPolicy::class);
        Gate::policy(NotificationLog::class, NotificationLogPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(PayoutRequest::class, PayoutRequestPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(StaffWallet::class, StaffWalletPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);

        Event::listen(MallOrderPaid::class, SendMallOrderPaidEmails::class);
        Event::listen(PaymentPaid::class, RecordRevenueLifecycleEvent::class);
        Event::listen(SubscriptionActivated::class, RecordRevenueLifecycleEvent::class);
        Event::listen(PayoutPaid::class, RecordRevenueLifecycleEvent::class);
        Event::listen(PushCampaignDispatched::class, RecordRevenueLifecycleEvent::class);

        Article::observe(QueueContentTranslations::class);
        Article::observe(QueueArticleKnowledge::class);
        Category::observe(QueueContentTranslations::class);
        Classified::observe(QueueContentTranslations::class);
        Classified::observe(QueuePublicKnowledge::class);
        LifeEvent::observe(QueueContentTranslations::class);
        LifeEvent::observe(QueuePublicKnowledge::class);
        Listing::observe(QueueContentTranslations::class);
        Listing::observe(QueuePublicKnowledge::class);
        LocationNode::observe(QueueContentTranslations::class);
        Tag::observe(QueueContentTranslations::class);
        Voucher::observe(QueueContentTranslations::class);
        Voucher::observe(QueuePublicKnowledge::class);
        CivicFaultReport::observe(QueuePublicKnowledge::class);

        $this->configureRateLimiters();

        if (app()->environment('production')) {
            \URL::forceScheme('https');
        }

        if (config('database.default') === 'sqlite') {
            try {
                $db = \DB::connection()->getPdo();
                $db->sqliteCreateFunction('acos', 'acos', 1);
                $db->sqliteCreateFunction('cos', 'cos', 1);
                $db->sqliteCreateFunction('sin', 'sin', 1);
                $db->sqliteCreateFunction('radians', 'deg2rad', 1);
            } catch (\Exception $e) {
                // Silently fail if database is not available (e.g. during build)
            }
        }
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('auth-sensitive', function (Request $request) {
            return Limit::perMinute(6)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('public-form', function (Request $request) {
            return Limit::perMinute(5)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('voucher-redemption', function (Request $request) {
            return Limit::perMinute(12)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('payfast-callback', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip() ?: 'unknown');
        });

        RateLimiter::for('public-tracking', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip() ?: 'unknown');
        });

        RateLimiter::for('ask-life', function (Request $request) {
            $identity = implode('|', [
                (string) ($request->user()?->id ?: 'guest'),
                (string) $request->input('session_id', 'new'),
                $request->ip() ?: 'unknown',
            ]);

            return Limit::perMinute(20)->by(hash('sha256', $identity.'|'.(string) config('app.key')));
        });
    }

    private function rateLimitKey(Request $request): string
    {
        return ((string) ($request->user()?->id ?: 'guest')).'|'.($request->ip() ?: 'unknown');
    }
}
