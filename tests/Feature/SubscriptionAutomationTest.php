<?php

namespace Tests\Feature;

use App\Mail\RenewalPaymentReminderMail;
use App\Mail\SubscriptionExpiryReminderMail;
use App\Models\Entitlement;
use App\Models\Listing;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Package;
use App\Models\PushCampaign;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NotificationDispatchService;
use App\Services\PayFastCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiry_reminder_command_logs_reminders_for_near_expiry_subscriptions(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Expiry Listing',
            'slug' => 'expiry-listing',
            'status' => 'published',
        ]);

        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(5),
            'renews_at' => now()->addDays(5),
            'renewal_mode' => 'manual',
        ]);

        Artisan::call('subscriptions:send-expiry-reminders', ['--days' => 7]);

        $this->assertDatabaseHas('subscription_reminders', [
            'subscription_id' => $subscription->id,
            'reminder_type' => 'expiry_notice',
            'channel' => 'email',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'notification_type' => 'subscription_expiry_reminder',
            'notifiable_type' => Subscription::class,
            'notifiable_id' => $subscription->id,
            'status' => 'sent',
        ]);
        Mail::assertSent(SubscriptionExpiryReminderMail::class);
    }

    public function test_expiry_sweep_command_expires_subscription_and_deactivates_listing(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Expired Listing',
            'slug' => 'expired-listing',
            'status' => 'published',
        ]);

        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now()->subMonths(6),
            'ends_at' => now()->subDay(),
            'renews_at' => now()->subDay(),
            'renewal_mode' => 'manual',
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $subscription->ends_at,
        ]);

        Artisan::call('subscriptions:sweep-expired');

        $subscription->refresh();
        $listing->refresh();

        $this->assertSame('expired', $subscription->status);
        $this->assertSame('draft', $listing->status);
        $this->assertNull($listing->active_subscription_id);
    }

    public function test_user_can_open_renewal_checkout_from_subscription(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Renew Listing',
            'slug' => 'renew-listing',
            'status' => 'draft',
        ]);

        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'expired',
            'starts_at' => now()->subMonths(6),
            'ends_at' => now()->subDay(),
            'renewal_mode' => 'manual',
        ]);

        $response = $this->actingAs($owner)->get(route('checkout.subscriptions.renew', $subscription));

        $response->assertRedirect(route('checkout.index', [
            'package' => $package->slug,
            'listing' => $listing->slug,
            'renewal_subscription' => $subscription->id,
        ]));
    }

    public function test_user_can_complete_manual_listing_renewal_from_browser_checkout(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Browser Renewal Listing',
            'slug' => 'browser-renewal-listing',
            'status' => 'draft',
        ]);

        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'expired',
            'starts_at' => now()->subMonths(6),
            'ends_at' => now()->subDay(),
            'renews_at' => now()->subDay(),
            'renewal_mode' => 'manual',
        ]);

        Entitlement::create([
            'subscription_id' => $subscription->id,
            'entitled_type' => Listing::class,
            'entitled_id' => $listing->id,
            'entitlement_code' => 'business_directory',
            'active_from' => $subscription->starts_at,
            'active_until' => $subscription->ends_at,
            'status' => 'expired',
        ]);

        $renewalUrl = route('checkout.index', [
            'package' => $package->slug,
            'listing' => $listing->slug,
            'renewal_subscription' => $subscription->id,
        ]);

        $this->actingAs($owner)
            ->get(route('checkout.subscriptions.renew', $subscription))
            ->assertRedirect($renewalUrl);

        $this->actingAs($owner)
            ->get($renewalUrl)
            ->assertOk()
            ->assertSee('Renewing subscription:')
            ->assertSee('Create Renewal Order')
            ->assertSee($listing->title);

        $this->actingAs($owner)
            ->post(route('checkout.start'), [
                'package_slug' => $package->slug,
                'listing_slug' => $listing->slug,
                'renewal_subscription_id' => $subscription->id,
            ])
            ->assertRedirect();

        $order = Order::where('renewed_subscription_id', $subscription->id)->firstOrFail();

        $this->actingAs($owner)
            ->get(route('checkout.show', $order))
            ->assertOk()
            ->assertSee('Order Summary')
            ->assertSee('Renewal');

        $this->actingAs($owner)
            ->post(route('checkout.payfast.initiate', $order))
            ->assertRedirect(route('checkout.show', $order));

        $payment = $order->latestPayment();
        $this->assertNotNull($payment);
        $this->assertSame(1, $payment->attempts()->count());

        $payload = [
            'order_number' => $order->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-browser-renewal',
            'amount_gross' => number_format((float) $order->total, 2, '.', ''),
            'currency' => $order->currency,
        ];
        $payload['signature'] = app(PayFastCheckoutService::class)->generateSignature($payload);

        $this->post(route('checkout.payfast.callback'), $payload)->assertOk();

        $order->refresh();
        $listing->refresh();
        $renewedSubscription = $listing->activeSubscription()->firstOrFail();

        $this->assertSame('paid', $order->status);
        $this->assertSame('paid', $order->latestPayment()->fresh()->status);
        $this->assertSame($subscription->id, $order->renewed_subscription_id);
        $this->assertNotSame($subscription->id, $renewedSubscription->id);
        $this->assertSame('active', $renewedSubscription->status);
        $this->assertSame('published', $listing->status);
        $this->assertSame($renewedSubscription->id, $listing->active_subscription_id);
        $this->assertDatabaseHas('entitlements', [
            'subscription_id' => $renewedSubscription->id,
            'entitled_type' => Listing::class,
            'entitled_id' => $listing->id,
            'entitlement_code' => 'business_directory',
            'status' => 'active',
        ]);
    }

    public function test_user_cannot_start_checkout_for_another_users_renewal_subscription(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $otherUser = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Protected Renewal Listing',
            'slug' => 'protected-renewal-listing',
            'status' => 'draft',
        ]);

        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'expired',
            'starts_at' => now()->subMonths(6),
            'ends_at' => now()->subDay(),
            'renews_at' => now()->subDay(),
            'renewal_mode' => 'manual',
        ]);

        $this->actingAs($otherUser)
            ->get(route('checkout.subscriptions.renew', $subscription))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('checkout.start'), [
                'package_slug' => $package->slug,
                'listing_slug' => $listing->slug,
                'renewal_subscription_id' => $subscription->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('orders', [
            'renewed_subscription_id' => $subscription->id,
        ]);
    }

    public function test_admin_can_open_finance_detail_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.finance.orders.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.finance.notifications.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.finance.payments.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.finance.subscriptions.index'))->assertOk();
    }

    public function test_auto_renewal_command_creates_pending_order_for_auto_subscription(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Auto Renew Listing',
            'slug' => 'auto-renew-listing',
            'status' => 'published',
        ]);

        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now()->subMonths(6),
            'ends_at' => now()->addDay(),
            'renews_at' => now()->addHours(6),
            'renewal_mode' => 'auto',
        ]);

        Artisan::call('subscriptions:create-renewal-orders', ['--days' => 1]);

        $order = Order::where('renewed_subscription_id', $subscription->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('pending_payment', $order->status);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'package_id' => $package->id,
            'purchasable_type' => Listing::class,
            'purchasable_id' => $listing->id,
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'payment_id' => $order->latestPayment()->id,
            'status' => 'initiated',
        ]);
    }

    public function test_admin_can_open_finance_show_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Detail Listing',
            'slug' => 'detail-listing',
            'status' => 'published',
        ]);

        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'renews_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ]);

        $order = Order::create([
            'user_id' => $owner->id,
            'renewed_subscription_id' => $subscription->id,
            'order_number' => 'ORD-DETAIL-1',
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
        ]);

        $payment = $order->payments()->create([
            'user_id' => $owner->id,
            'provider' => 'payfast',
            'status' => 'pending',
            'amount' => 500,
            'currency' => 'ZAR',
        ]);

        $this->actingAs($admin)->get(route('admin.finance.orders.show', $order))->assertOk()->assertSee('Filter Timeline');
        $this->actingAs($admin)->get(route('admin.finance.payments.show', $payment))->assertOk()->assertSee('Filter Timeline');
        $this->actingAs($admin)->get(route('admin.finance.subscriptions.show', $subscription))->assertOk()->assertSee('Filter Timeline');
    }

    public function test_expired_listing_is_hidden_from_public_directory(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Expired Public Listing',
            'slug' => 'expired-public-listing',
            'status' => 'published',
        ]);

        $response = $this->get(route('directory.index'));
        $response->assertOk();
        $response->assertDontSee('Expired Public Listing');

        $this->get(route('directory.show', $listing))->assertNotFound();
    }

    public function test_expired_event_is_hidden_from_public_events(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Expired Event Host',
            'slug' => 'expired-event-host',
            'status' => 'published',
        ]);

        $event = \App\Models\Event::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Expired Public Event',
            'slug' => 'expired-public-event',
            'start_at' => now()->addWeek(),
            'status' => 'published',
        ]);

        $response = $this->get(route('events.index'));
        $response->assertOk();
        $response->assertDontSee('Expired Public Event');

        $this->get(route('events.show', $event))->assertNotFound();
    }

    public function test_unpaid_renewal_reminder_command_sends_mail_and_logs_notification(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Reminder Due Listing',
            'slug' => 'reminder-due-listing',
            'status' => 'published',
        ]);

        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now()->subMonths(6),
            'ends_at' => now()->addDay(),
            'renews_at' => now()->addHours(6),
            'renewal_mode' => 'auto',
        ]);

        Artisan::call('subscriptions:create-renewal-orders', ['--days' => 1]);

        $order = Order::where('renewed_subscription_id', $subscription->id)->firstOrFail();
        Order::whereKey($order->id)->update(['created_at' => now()->subDays(2)]);

        Artisan::call('renewals:send-payment-reminders', ['--hours' => 24]);

        Mail::assertSent(RenewalPaymentReminderMail::class);
        $this->assertDatabaseHas('notification_logs', [
            'notification_type' => 'renewal_payment_reminder',
            'notifiable_type' => Order::class,
            'notifiable_id' => $order->id,
            'status' => 'sent',
        ]);
    }

    public function test_due_push_campaign_command_logs_push_delivery_and_marks_campaign_sent(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Push Automation Listing',
            'slug' => 'push-automation-listing',
            'status' => 'published',
            'city' => 'Bethlehem',
            'region' => 'Free State',
        ]);

        $directoryPackage = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $listingSubscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $directoryPackage->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ]);

        $listing->update([
            'active_subscription_id' => $listingSubscription->id,
        ]);

        $campaign = PushCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Automation Push Campaign',
            'slug' => 'automation-push-campaign',
            'headline' => 'Big weekend',
            'message' => 'Come through for a weekend special.',
            'schedule_at' => now()->subMinutes(30),
            'audience_scope' => 'listing_city',
            'target_city' => 'Bethlehem',
            'target_region' => 'Free State',
            'status' => 'scheduled',
            'published_at' => now()->subHour(),
        ]);

        $pushPackage = Package::where('slug', 'push-campaign-once')->firstOrFail();
        $pushSubscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $pushPackage->id,
            'subscribable_type' => PushCampaign::class,
            'subscribable_id' => $campaign->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ]);

        $campaign->update([
            'active_subscription_id' => $pushSubscription->id,
        ]);

        Artisan::call('push-campaigns:dispatch-due');

        $campaign->refresh();

        $this->assertNotNull($campaign->sent_at);
        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'push',
            'notification_type' => 'push_campaign_sent',
            'notifiable_type' => PushCampaign::class,
            'notifiable_id' => $campaign->id,
            'status' => 'sent',
        ]);
    }

    public function test_admin_can_view_and_resend_notification(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $order = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'ORD-NOTIFY-1',
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
        ]);

        $notification = NotificationLog::create([
            'channel' => 'email',
            'notification_type' => 'renewal_payment_reminder',
            'notifiable_type' => Order::class,
            'notifiable_id' => $order->id,
            'recipient' => $owner->email,
            'status' => 'sent',
            'sent_at' => now(),
            'meta_json' => ['order_number' => $order->order_number],
        ]);

        NotificationLog::whereKey($notification->id)->update([
            'sent_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
        ]);
        $notification->refresh();

        $this->actingAs($admin)->get(route('admin.finance.notifications.show', $notification))
            ->assertOk()
            ->assertSee('Resend Notification');

        $this->actingAs($admin)->post(route('admin.finance.notifications.resend', $notification))
            ->assertRedirect(route('admin.finance.notifications.show', $notification));

        Mail::assertSent(RenewalPaymentReminderMail::class);
        $this->assertDatabaseHas('notification_logs', [
            'notification_type' => 'renewal_payment_reminder',
            'notifiable_type' => Order::class,
            'notifiable_id' => $order->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'notification.resent',
            'subject_type' => NotificationLog::class,
            'subject_id' => $notification->id,
        ]);
    }

    public function test_push_delivery_logs_are_visible_but_not_resendable_in_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $campaign = PushCampaign::create([
            'listing_id' => Listing::create([
                'user_id' => $owner->id,
                'title' => 'Push Log Listing',
                'slug' => 'push-log-listing',
                'status' => 'published',
            ])->id,
            'user_id' => $owner->id,
            'title' => 'Push Log Campaign',
            'slug' => 'push-log-campaign',
            'message' => 'Testing push delivery history.',
            'audience_scope' => 'listing_city',
            'status' => 'active',
        ]);

        $notification = NotificationLog::create([
            'channel' => 'push',
            'notification_type' => 'push_campaign_sent',
            'notifiable_type' => PushCampaign::class,
            'notifiable_id' => $campaign->id,
            'recipient' => 'Listing city: Bethlehem',
            'status' => 'sent',
            'sent_at' => now(),
            'meta_json' => ['campaign_title' => $campaign->title],
        ]);

        $this->actingAs($admin)->get(route('admin.finance.notifications.show', $notification))
            ->assertOk()
            ->assertSee('delivery log only')
            ->assertDontSee('Resend Notification');
    }

    public function test_recent_notification_cannot_be_resent_immediately(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $order = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'ORD-NOTIFY-2',
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
        ]);

        $notification = NotificationLog::create([
            'channel' => 'email',
            'notification_type' => 'renewal_payment_reminder',
            'notifiable_type' => Order::class,
            'notifiable_id' => $order->id,
            'recipient' => $owner->email,
            'status' => 'sent',
            'sent_at' => now(),
            'meta_json' => ['order_number' => $order->order_number],
        ]);

        $this->actingAs($admin)->get(route('admin.finance.notifications.show', $notification))
            ->assertOk()
            ->assertSee('Resend available after');

        $this->actingAs($admin)->post(route('admin.finance.notifications.resend', $notification))
            ->assertRedirect(route('admin.finance.notifications.show', $notification));

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'notification.resent',
            'subject_type' => NotificationLog::class,
            'subject_id' => $notification->id,
        ]);
    }

    public function test_failed_notification_dispatch_is_logged_with_failed_status(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $order = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'ORD-NOTIFY-FAIL',
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
        ]);

        Mail::shouldReceive('to->send')->once()->andThrow(new \Exception('SMTP unavailable'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Notification delivery failed');

        try {
            app(NotificationDispatchService::class)->sendRenewalPaymentReminder($order);
        } finally {
            $this->assertDatabaseHas('notification_logs', [
                'notification_type' => 'renewal_payment_reminder',
                'notifiable_type' => Order::class,
                'notifiable_id' => $order->id,
                'status' => 'failed',
            ]);
        }
    }
}
