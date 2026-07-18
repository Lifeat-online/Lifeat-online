<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

class ProductionReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_check_reports_non_production_runtime(): void
    {
        config()->set('app.debug', true);
        Env::getRepository()->clear('UPLOAD_STORAGE_BACKEND');
        Env::getRepository()->clear('UPLOAD_STORAGE_MOUNT_PATH');
        putenv('UPLOAD_STORAGE_BACKEND');
        putenv('UPLOAD_STORAGE_MOUNT_PATH');
        unset($_ENV['UPLOAD_STORAGE_BACKEND'], $_ENV['UPLOAD_STORAGE_MOUNT_PATH']);
        unset($_SERVER['UPLOAD_STORAGE_BACKEND'], $_SERVER['UPLOAD_STORAGE_MOUNT_PATH']);

        $this->artisan('production:check')
            ->expectsOutputToContain('[APP_ENV] APP_ENV must be production.')
            ->expectsOutputToContain('[APP_DEBUG] APP_DEBUG must be false.')
            ->expectsOutputToContain('[QUEUE_WORKER_ENABLED] A production queue worker process must be configured and running.')
            ->expectsOutputToContain('[SCHEDULER_ENABLED] A production scheduler or cron process must be configured and running.')
            ->expectsOutputToContain('[UPLOAD_STORAGE_BACKEND] Set UPLOAD_STORAGE_BACKEND to mounted_volume before launch, or refactor uploads to dedicated object-storage disks.')
            ->expectsOutputToContain('[BACKUPS_ENABLED] Automated production database backups must be enabled.')
            ->expectsOutputToContain('[ERROR_TRACKING_ENABLED] ERROR_TRACKING_ENABLED must be true before public launch.')
            ->assertExitCode(1);
    }

    public function test_scheduler_lists_production_recurring_jobs(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('subscriptions:send-expiry-reminders --days=7')
            ->expectsOutputToContain('subscriptions:create-renewal-orders --days=1')
            ->expectsOutputToContain('renewals:send-payment-reminders --hours=24')
            ->expectsOutputToContain('subscriptions:sweep-expired')
            ->expectsOutputToContain('push-campaigns:dispatch-due')
            ->expectsOutputToContain('life:research:collect --limit=25')
            ->expectsOutputToContain('life:editorial:brief --limit=10')
            ->expectsOutputToContain('life:jimmy:write --limit=3')
            ->expectsOutputToContain('life:images:generate --limit=3')
            ->assertExitCode(0);
    }

    public function test_production_check_warns_when_translation_queue_needs_worker_command(): void
    {
        config([
            'localization.auto_translate_on_publish' => true,
            'localization.auto_translation_queue' => 'translations',
        ]);
        Env::getRepository()->set('QUEUE_WORKER_COMMAND', 'php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=120');
        Env::getRepository()->clear('AUTO_TRANSLATION_WORKER_COMMAND');
        putenv('AUTO_TRANSLATION_WORKER_COMMAND');
        unset($_ENV['AUTO_TRANSLATION_WORKER_COMMAND'], $_SERVER['AUTO_TRANSLATION_WORKER_COMMAND']);

        try {
            $this->artisan('production:check')
                ->expectsOutputToContain('[AUTO_TRANSLATION_WORKER_COMMAND] AUTO_TRANSLATION_QUEUE is translations, so document a worker command that listens to that queue.')
                ->assertExitCode(1);
        } finally {
            Env::getRepository()->clear('QUEUE_WORKER_COMMAND');
            Env::getRepository()->clear('AUTO_TRANSLATION_WORKER_COMMAND');
            putenv('QUEUE_WORKER_COMMAND');
            putenv('AUTO_TRANSLATION_WORKER_COMMAND');
            unset($_ENV['QUEUE_WORKER_COMMAND'], $_ENV['AUTO_TRANSLATION_WORKER_COMMAND']);
            unset($_SERVER['QUEUE_WORKER_COMMAND'], $_SERVER['AUTO_TRANSLATION_WORKER_COMMAND']);
        }
    }
}
