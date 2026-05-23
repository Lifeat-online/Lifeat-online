<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_check_reports_non_production_runtime(): void
    {
        $this->artisan('production:check')
            ->expectsOutputToContain('[APP_ENV] APP_ENV must be production.')
            ->expectsOutputToContain('[APP_DEBUG] APP_DEBUG must be false.')
            ->expectsOutputToContain('[QUEUE_WORKER_ENABLED] A production queue worker process must be configured and running.')
            ->expectsOutputToContain('[SCHEDULER_ENABLED] A production scheduler or cron process must be configured and running.')
            ->expectsOutputToContain('[UPLOAD_STORAGE_BACKEND] Set UPLOAD_STORAGE_BACKEND to railway_volume before launch, or refactor uploads to dedicated object-storage disks.')
            ->expectsOutputToContain('[BACKUPS_ENABLED] Automated production database backups must be enabled.')
            ->expectsOutputToContain('[BACKUP_RESTORE_DRILL_COMPLETED] A production-like backup restore drill must be completed before accepting real payments.')
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
}
