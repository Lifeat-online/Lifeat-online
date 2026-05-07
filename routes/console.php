<?php

use App\Models\Order;
use App\Models\PushCampaign;
use App\Models\Subscription;
use App\Services\NotificationDispatchService;
use App\Services\PushCampaignDispatchService;
use App\Services\SubscriptionLifecycleService;
use App\Services\SubscriptionRenewalService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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

Artisan::command('civic:bootstrap-admin {email} {--name=} {--role=super_admin}', function () {
    $email = (string) $this->argument('email');
    $name = (string) ($this->option('name') ?: 'Admin User');
    $role = (string) ($this->option('role') ?: 'super_admin');

    $user = \App\Models\User::query()->where('email', $email)->first();

    $attributes = [
        'name' => $name,
        'email' => $email,
        'password' => Hash::make(Str::random(40)),
        'role' => $role,
        'email_verified_at' => now(),
    ];

    if ($user) {
        $user->forceFill($attributes)->save();
    } else {
        $user = \App\Models\User::create($attributes);
    }

    $token = Password::broker()->createToken($user);
    $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);

    $this->info('Admin user ensured: '.$user->email);
    $this->info('Role: '.$user->role);
    $this->line('Password setup link (one-time): '.$resetUrl);
})->purpose('Create or update an admin user and output a one-time password reset link');
