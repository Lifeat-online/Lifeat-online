<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageType;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSupportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_user_can_access_customer_lookup_and_finance_read_screens(): void
    {
        $support = User::factory()->create([
            'role' => 'support',
            'name' => 'Support Agent',
        ]);

        $customer = User::factory()->create([
            'name' => 'Customer Person',
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-SUPPORT-1',
            'status' => 'paid',
            'currency' => 'ZAR',
            'subtotal' => 300,
            'vat_amount' => 0,
            'total' => 300,
            'placed_at' => now(),
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'provider' => 'manual',
            'status' => 'paid',
            'amount' => 300,
            'currency' => 'ZAR',
            'provider_transaction_id' => 'TX-SUPPORT-1',
            'paid_at' => now(),
        ]);

        $notification = NotificationLog::create([
            'channel' => 'email',
            'notification_type' => 'payment_receipt',
            'notifiable_type' => Payment::class,
            'notifiable_id' => $payment->id,
            'recipient' => $customer->email,
            'status' => 'sent',
            'sent_at' => now(),
            'meta_json' => [],
        ]);

        $customerLookupResponse = $this->actingAs($support)->get(route('admin.customers.show', $customer));
        $financeIndexResponse = $this->actingAs($support)->get(route('admin.finance.index'));
        $notificationShowResponse = $this->actingAs($support)->get(route('admin.finance.notifications.show', $notification));

        $customerLookupResponse->assertOk();
        $customerLookupResponse->assertSee('Customer Customer Person');

        $financeIndexResponse->assertOk();
        $financeIndexResponse->assertSee('Finance Dashboard');
        $financeIndexResponse->assertDontSee('Export Orders');
        $financeIndexResponse->assertDontSee('Mark Paid');

        $notificationShowResponse->assertOk();
        $notificationShowResponse->assertSee('Notification '.$notification->id);
        $notificationShowResponse->assertSee('Read-only access.');
    }

    public function test_support_user_sees_support_focused_dashboard_without_create_actions(): void
    {
        $support = User::factory()->create([
            'role' => 'support',
            'name' => 'Support Dashboard User',
        ]);

        $customer = User::factory()->create([
            'name' => 'Queue Customer',
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-SUPPORT-QUEUE',
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 450,
            'vat_amount' => 0,
            'total' => 450,
            'placed_at' => now(),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'provider' => 'manual',
            'status' => 'failed',
            'amount' => 450,
            'currency' => 'ZAR',
            'provider_transaction_id' => 'TX-SUPPORT-QUEUE',
        ]);

        $packageType = PackageType::firstOrCreate([
            'slug' => 'business_directory',
        ], [
            'name' => 'Business Directory',
            'description' => 'Directory packages',
        ]);

        $package = Package::firstOrCreate([
            'slug' => 'business-directory-standard-6m',
        ], [
            'package_type_id' => $packageType->id,
            'name' => 'Business Directory Standard',
            'description' => 'Standard package',
            'billing_model' => 'fixed',
            'is_self_service' => false,
            'duration_days' => 180,
            'status' => 'active',
        ]);

        $listing = Listing::create([
            'user_id' => $customer->id,
            'source_channel' => 'staff_assisted',
            'title' => 'Queue Listing',
            'slug' => 'queue-listing',
            'status' => 'draft',
        ]);

        Subscription::create([
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(3),
        ]);

        NotificationLog::create([
            'channel' => 'email',
            'notification_type' => 'renewal_payment_reminder',
            'notifiable_type' => Order::class,
            'notifiable_id' => $order->id,
            'recipient' => $customer->email,
            'status' => 'pending',
            'sent_at' => now(),
            'meta_json' => [],
        ]);

        $response = $this->actingAs($support)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Support Workspace');
        $response->assertSee('Support Priorities');
        $response->assertSee('Open Customer Lookup');
        $response->assertSee('Review Payments');
        $response->assertSee('Failed Payments');
        $response->assertSee('Pending Notifications');
        $response->assertSee('Expiring In 7 Days');
        $response->assertSee('Failed Payments Queue');
        $response->assertSee('Notification Queue');
        $response->assertSee('Expiring Subscriptions');
        $response->assertSee('ORD-SUPPORT-QUEUE');
        $response->assertSee('Renewal payment reminder');
        $response->assertSee('Queue Customer');
        $response->assertSee(route('admin.finance.payments.index', ['status' => 'failed']), false);
        $response->assertSee(route('admin.finance.notifications.index', ['status' => 'attention']), false);
        $response->assertSee(route('admin.finance.subscriptions.index', ['status' => 'active', 'ending_within_days' => 7]));
        $response->assertDontSee('Developer Control Center');
        $response->assertDontSee('Testing Area');
        $response->assertDontSee('New Listing');
        $response->assertDontSee('New Event');
        $response->assertDontSee('New Article');
        $response->assertDontSee('Review Applications');
    }

    public function test_dev_owner_sees_dev_tab_sections_on_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'dev',
            'name' => 'Admin Dev User',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Management Area');
        $response->assertSee('Dev');
        $response->assertSee('Dev Dashboard');
        $response->assertSee('Full screen');
        $response->assertSee('Developer Control Center');
        $response->assertSee('Push Notification Setup');
        $response->assertSee(route('dev.webpush.vapid.enable'), false);
        $response->assertSee('Live Metrics');
        $response->assertSee('Testing Area');
        $response->assertSee('Roles And Permissions');
        $response->assertSee('Server Statistics');
    }

    public function test_non_owner_admin_does_not_see_dev_tab_sections_on_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Regular User',
            'email' => 'other-admin@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Management Area');
        $response->assertDontSee('Dev');
        $response->assertDontSee('Dev Dashboard');
        $response->assertDontSee('Developer Control Center');
        $response->assertDontSee('Push Notification Setup');
        $response->assertDontSee(route('dev.webpush.vapid.enable'), false);
    }

    public function test_support_user_cannot_use_write_finance_actions(): void
    {
        $support = User::factory()->create([
            'role' => 'support',
        ]);

        $customer = User::factory()->create();

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-SUPPORT-2',
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 200,
            'vat_amount' => 0,
            'total' => 200,
            'placed_at' => now(),
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'provider' => 'manual',
            'status' => 'pending',
            'amount' => 200,
            'currency' => 'ZAR',
        ]);

        $response = $this->actingAs($support)->post(route('admin.finance.payments.mark-paid', $payment));

        $response->assertForbidden();
    }

    public function test_support_user_cannot_run_dev_test_suite(): void
    {
        $support = User::factory()->create([
            'role' => 'support',
        ]);

        $response = $this->actingAs($support)->postJson(route('dev.tests.run'), [
            'suite' => 'Unit',
        ]);

        $response->assertForbidden();
    }
}
