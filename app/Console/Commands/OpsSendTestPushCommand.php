<?php

namespace App\Console\Commands;

use App\Services\OperatorPushNotifier;
use Illuminate\Console\Command;

class OpsSendTestPushCommand extends Command
{
    protected $signature = 'ops:send-test-push
        {--user= : Send to a single user ID (overrides roster)}
        {--roster=admin : When --user is empty, resolve the roster. admin|operational|all|business}
        {--target=monitoring:degraded : Target name (must exist in config/ops.php)}
        {--title= : Override the notification title}
        {--body= : Override the notification body}
        {--severity=info : Severity (critical|warning|info)}';

    protected $description = 'Dry-run or live-send a push notification to the operator roster.';

    public function handle(OperatorPushNotifier $notifier): int
    {
        $target = (string) $this->option('target');
        $title = (string) ($this->option('title') ?? 'Operator test alert');
        $body = (string) ($this->option('body') ?? 'This is a test push from the ops pipeline.');
        $severity = (string) $this->option('severity');

        if (! array_key_exists($target, (array) config('ops.targets', []))) {
            $this->error("Unknown target '{$target}'. See config/ops.php for the full list.");

            return self::FAILURE;
        }

        if ($userId = $this->option('user')) {
            $recipients = \App\Models\User::where('id', (int) $userId)->get();
        } else {
            $recipients = $notifier->recipientsFor($target);
        }

        $this->info(sprintf(
            'Target %s | severity=%s | recipients=%d | configured=%s',
            $target,
            $severity,
            $recipients->count(),
            $notifier->isConfigured() ? 'yes' : 'no'
        ));

        $this->table(
            ['id', 'name', 'email', 'role', 'subscriptions'],
            $recipients->map(fn ($u) => [
                $u->id,
                $u->name,
                $u->email,
                $u->role,
                $u->browserPushSubscriptions()->whereNull('revoked_at')->count(),
            ])->all()
        );

        if (! $notifier->isConfigured()) {
            $this->warn('VAPID keys are not configured. Re-run `php artisan webpush:keys` and set WEBPUSH_VAPID_PUBLIC_KEY + WEBPUSH_VAPID_PRIVATE_KEY in .env.');

            return self::SUCCESS;
        }

        $result = $notifier->send($target, $title, $body, $severity);

        $this->info(json_encode($result, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
