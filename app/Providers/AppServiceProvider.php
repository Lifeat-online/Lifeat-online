<?php

namespace App\Providers;

use App\Events\MallOrderPaid;
use App\Listeners\SendMallOrderPaidEmails;
use App\Models\Article;
use App\Models\Category;
use App\Models\Classified;
use App\Models\Event as LifeEvent;
use App\Models\Listing;
use App\Models\LocationNode;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Payment;
use App\Models\StaffWallet;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\Voucher;
use App\Observers\QueueContentTranslations;
use App\Policies\ListingPolicy;
use App\Policies\NotificationLogPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PayoutRequestPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\StaffWalletPolicy;
use App\Policies\SubscriptionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        Article::observe(QueueContentTranslations::class);
        Category::observe(QueueContentTranslations::class);
        Classified::observe(QueueContentTranslations::class);
        LifeEvent::observe(QueueContentTranslations::class);
        Listing::observe(QueueContentTranslations::class);
        LocationNode::observe(QueueContentTranslations::class);
        Tag::observe(QueueContentTranslations::class);
        Voucher::observe(QueueContentTranslations::class);

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
    }

    private function rateLimitKey(Request $request): string
    {
        return ((string) ($request->user()?->id ?: 'guest')).'|'.($request->ip() ?: 'unknown');
    }
}
