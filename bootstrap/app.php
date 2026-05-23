<?php

use App\Http\Middleware\EnsureTransportDriverOnDuty;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TranslateInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

foreach (['SIGINT' => 2, 'SIGTERM' => 15, 'SIGTSTP' => 20] as $signal => $value) {
    if (! defined($signal)) {
        define($signal, $value);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('subscriptions:send-expiry-reminders --days=7')->dailyAt('08:00');
        $schedule->command('subscriptions:create-renewal-orders --days=1')->dailyAt('08:15');
        $schedule->command('renewals:send-payment-reminders --hours=24')->dailyAt('09:00');
        $schedule->command('subscriptions:sweep-expired')->hourly();
        $schedule->command('push-campaigns:dispatch-due')->everyFifteenMinutes();
        $schedule->command('life:research:collect --limit=25')->hourlyAt(10);
        $schedule->command('life:editorial:brief --limit=10')->hourlyAt(20);
        $schedule->command('life:jimmy:write --limit=3')->hourlyAt(30);
        $schedule->command('life:images:generate --limit=3')->hourlyAt(40);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            SetLocale::class,
            TranslateInterface::class,
        ]);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'transport.on_duty' => EnsureTransportDriverOnDuty::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
