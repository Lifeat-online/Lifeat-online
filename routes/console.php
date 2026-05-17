<?php

use App\Models\Order;
use App\Models\PushCampaign;
use App\Models\Subscription;
use App\Services\NotificationDispatchService;
use App\Services\PushCampaignDispatchService;
use App\Services\SubscriptionLifecycleService;
use App\Services\SubscriptionRenewalService;
use App\Support\ProductionReadiness\EnvironmentCheck;
use App\Support\Uploads\UploadReferenceIndex;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Laravel\Pulse\Pulse;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Loggers\CliLogger;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\ServerProviderManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use Laravel\Reverb\Servers\Reverb\Factory as ReverbServerFactory;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Telescope;
use React\EventLoop\Loop;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reverb:start-railway
    {--host= : The IP address the server should bind to}
    {--port= : The port the server should listen on}
    {--path= : The path the server should prefix to all routes}
    {--hostname= : The hostname the server is accessible from}
    {--debug : Indicates whether debug messages should be displayed in the terminal}', function () {
    if ($this->option('debug')) {
        app()->instance(Logger::class, new CliLogger($this->output));
    }

    $config = app('config')['reverb.servers.reverb'];
    $loop = Loop::get();

    $server = ReverbServerFactory::make(
        $host = $this->option('host') ?: $config['host'],
        $port = $this->option('port') ?: $config['port'],
        $path = $this->option('path') ?: $config['path'] ?? '',
        $hostname = $this->option('hostname') ?: $config['hostname'],
        $config['max_request_size'] ?? 10_000,
        $config['options'] ?? [],
        loop: $loop
    );

    if (app(ServerProviderManager::class)->driver('reverb')->subscribesToEvents()) {
        app(PubSubProvider::class)->connect($loop);
    }

    $loop->addPeriodicTimer(60, function () {
        PruneStaleConnections::dispatch();
        PingInactiveConnections::dispatch();
    });

    $lastRestart = Cache::get('laravel:reverb:restart');
    $loop->addPeriodicTimer(5, function () use ($server, $host, $port, $lastRestart) {
        if ($lastRestart === Cache::get('laravel:reverb:restart')) {
            return;
        }

        app(ApplicationProvider::class)
            ->all()
            ->each(function ($application) {
                collect(app(ChannelManager::class)->for($application)->connections())
                    ->each
                    ->disconnect();
            });

        $server->stop();
        $this->components->info("Stopping server on {$host}:{$port}");
    });

    if (app()->bound(Pulse::class)) {
        $loop->addPeriodicTimer($config['pulse_ingest_interval'], fn () => app(Pulse::class)->ingest());
    }

    if (app()->bound(EntriesRepository::class)) {
        $loop->addPeriodicTimer($config['telescope_ingest_interval'] ?? 15, fn () => Telescope::store(app(EntriesRepository::class)));
    }

    $this->components->info('Starting '.($server->isSecure() ? 'secure ' : '')."server on {$host}:{$port}{$path}".(($hostname && $hostname !== $host) ? " ({$hostname})" : ''));

    $server->start();
})->purpose('Start the Reverb server on hosts without pcntl signal support');

Artisan::command('production:check {--fail-on-warning : Return a failing exit code when warnings are present}', function (EnvironmentCheck $check) {
    $findings = $check->run();
    $errors = array_filter($findings, fn (array $finding) => $finding['level'] === 'error');
    $warnings = array_filter($findings, fn (array $finding) => $finding['level'] === 'warning');

    if ($findings === []) {
        $this->info('Production readiness checks passed.');

        return Command::SUCCESS;
    }

    foreach ($findings as $finding) {
        $line = "[{$finding['key']}] {$finding['message']}";

        if ($finding['level'] === 'error') {
            $this->error($line);
        } else {
            $this->warn($line);
        }
    }

    $this->newLine();
    $this->line(count($errors).' error(s), '.count($warnings).' warning(s).');

    if ($errors !== [] || ($this->option('fail-on-warning') && $warnings !== [])) {
        return Command::FAILURE;
    }

    return Command::SUCCESS;
})->purpose('Check production environment settings before deployment');

Artisan::command('uploads:orphans {--disk=public : Storage disk to scan: public or local} {--delete : Delete orphaned files instead of only listing them}', function (UploadReferenceIndex $index) {
    $disk = (string) $this->option('disk');

    if (! in_array($disk, ['public', 'local'], true)) {
        $this->error('The --disk option must be public or local.');

        return Command::FAILURE;
    }

    $orphans = $index->orphaned($disk);

    if ($orphans === []) {
        $this->info("No orphaned {$disk} upload files found.");

        return Command::SUCCESS;
    }

    foreach ($orphans as $path) {
        $this->line($path);
    }

    if ($this->option('delete')) {
        Storage::disk($disk)->delete($orphans);
        $this->warn('Deleted '.count($orphans)." orphaned {$disk} upload file(s).");

        return Command::SUCCESS;
    }

    $this->warn('Found '.count($orphans)." orphaned {$disk} upload file(s). Run again with --delete to remove them.");

    return Command::FAILURE;
})->purpose('Find or delete upload files that are no longer referenced by database records');

Artisan::command('subscriptions:send-expiry-reminders {--days=7}', function (SubscriptionLifecycleService $lifecycleService, NotificationDispatchService $notificationDispatchService) {
    $days = (int) $this->option('days');

    $subscriptions = Subscription::with(['user', 'package'])
        ->where('status', 'active')
        ->whereNotNull('ends_at')
        ->whereBetween('ends_at', [now(), now()->copy()->addDays($days)])
        ->get();

    $count = 0;

    foreach ($subscriptions as $subscription) {
        $alreadyLogged = $subscription->reminders()
            ->where('reminder_type', 'expiry_notice')
            ->whereDate('created_at', today())
            ->exists();

        if ($alreadyLogged) {
            continue;
        }

        $reminderStatus = 'logged';

        if ($subscription->user?->email) {
            try {
                $notification = $notificationDispatchService->sendSubscriptionExpiryReminder($subscription);
                $reminderStatus = $notification->status;
            } catch (\RuntimeException) {
                $reminderStatus = 'failed';
            }
        }

        $lifecycleService->logReminder($subscription, 'expiry_notice', 'email', $reminderStatus);
        $count++;
    }

    $this->info("Logged {$count} expiry reminders.");
})->purpose('Log expiry reminders for subscriptions nearing expiry');

Artisan::command('subscriptions:sweep-expired', function (SubscriptionLifecycleService $lifecycleService) {
    $subscriptions = Subscription::whereIn('status', ['active', 'pending'])
        ->whereNotNull('ends_at')
        ->where('ends_at', '<', now())
        ->get();

    $count = 0;

    foreach ($subscriptions as $subscription) {
        $lifecycleService->expire($subscription);
        $count++;
    }

    $this->info("Expired {$count} subscriptions.");
})->purpose('Expire subscriptions whose end date has passed');

Artisan::command('subscriptions:create-renewal-orders {--days=1}', function (SubscriptionRenewalService $renewalService) {
    $days = (int) $this->option('days');

    $subscriptions = Subscription::with(['package.prices', 'subscribable', 'user'])
        ->where('status', 'active')
        ->where('renewal_mode', 'auto')
        ->whereNotNull('renews_at')
        ->whereBetween('renews_at', [now(), now()->copy()->addDays($days)])
        ->get();

    $count = 0;

    foreach ($subscriptions as $subscription) {
        $renewalService->createRenewalOrder($subscription, true);
        $count++;
    }

    $this->info("Created {$count} renewal orders.");
})->purpose('Create renewal orders for subscriptions nearing auto-renewal');

Artisan::command('renewals:send-payment-reminders {--hours=24}', function (NotificationDispatchService $notificationDispatchService) {
    $hours = (int) $this->option('hours');

    $orders = Order::with('user')
        ->whereNotNull('renewed_subscription_id')
        ->where('status', 'pending_payment')
        ->where('created_at', '<=', now()->subHours($hours))
        ->get();

    $count = 0;

    foreach ($orders as $order) {
        $alreadyLogged = \App\Models\NotificationLog::query()
            ->where('notification_type', 'renewal_payment_reminder')
            ->where('notifiable_type', Order::class)
            ->where('notifiable_id', $order->id)
            ->whereDate('created_at', today())
            ->exists();

        if ($alreadyLogged || ! $order->user?->email) {
            continue;
        }

        try {
            $notificationDispatchService->sendRenewalPaymentReminder($order);
            $count++;
        } catch (\RuntimeException) {
            // Failure is already logged for admin follow-up.
        }
    }

    $this->info("Sent {$count} renewal payment reminders.");
})->purpose('Send payment reminders for unpaid renewal orders');

Artisan::command('push-campaigns:dispatch-due', function (PushCampaignDispatchService $dispatchService) {
    $campaigns = PushCampaign::with(['listing', 'event', 'activeSubscription.package'])
        ->whereNull('sent_at')
        ->whereIn('status', ['active', 'scheduled'])
        ->where(function ($query) {
            $query->where('status', 'active')
                ->orWhere(function ($scheduled) {
                    $scheduled->where('status', 'scheduled')
                        ->whereNotNull('schedule_at')
                        ->where('schedule_at', '<=', now());
                });
        })
        ->get();

    $count = 0;

    foreach ($campaigns as $campaign) {
        try {
            $dispatchService->dispatch($campaign);
            $count++;
        } catch (\RuntimeException) {
            // Invalid campaigns remain unsent for manual correction.
        }
    }

    $this->info("Dispatched {$count} push campaigns.");
})->purpose('Dispatch due push campaigns that hold valid entitlements');
