<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('subscriptions:send-expiry-reminders --days=7')->dailyAt('08:00');
        $schedule->command('subscriptions:create-renewal-orders --days=1')->dailyAt('08:15');
        $schedule->command('renewals:send-payment-reminders --hours=24')->dailyAt('09:00');
        $schedule->command('subscriptions:sweep-expired')->hourly();
        $schedule->command('push-campaigns:dispatch-due')->everyFifteenMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
