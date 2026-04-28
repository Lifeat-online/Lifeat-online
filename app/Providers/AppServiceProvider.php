<?php

namespace App\Providers;

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
}
