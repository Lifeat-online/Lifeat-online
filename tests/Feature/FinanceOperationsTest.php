<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_finance_datasets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.finance.export', 'payments'));

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_admin_can_mark_payment_paid_and_activate_listing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Finance Listing',
            'slug' => 'finance-listing',
            'status' => 'draft',
        ]);

        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $order = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'ORD-FIN-1',
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'package_id' => $package->id,
            'purchasable_type' => Listing::class,
            'purchasable_id' => $listing->id,
            'name_snapshot' => $package->name,
            'unit_price' => 500,
            'quantity' => 1,
            'billing_model' => 'six_monthly',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'provider' => 'manual',
            'status' => 'pending',
            'amount' => 500,
            'currency' => 'ZAR',
        ]);

        $this->actingAs($admin)->post(route('admin.finance.payments.mark-paid', $payment))
            ->assertRedirect(route('admin.finance.index'));

        $listing->refresh();
        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertSame('paid', $order->status);
        $this->assertSame('published', $listing->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.marked_paid',
            'subject_type' => Payment::class,
            'subject_id' => $payment->id,
        ]);
    }

    public function test_admin_can_record_refund(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);

        $order = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'ORD-REF-1',
            'status' => 'paid',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-REF-1',
            'invoice_prefix_snapshot' => 'LIFE',
            'status' => 'paid',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'provider' => 'manual',
            'status' => 'paid',
            'amount' => 500,
            'currency' => 'ZAR',
            'paid_at' => now(),
        ]);

        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Refunded Listing',
            'slug' => 'refunded-listing',
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
            'ends_at' => now()->addMonths(6),
            'renews_at' => now()->addMonths(6),
            'renewal_mode' => 'manual',
            'payment_id' => $payment->id,
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => now()->addMonths(6),
        ]);

        $this->actingAs($admin)->post(route('admin.finance.payments.refunds.store', $payment), [
            'refund_amount' => 500,
            'refund_reason' => 'Customer cancellation',
        ])->assertRedirect(route('admin.finance.index'));

        $payment->refresh();
        $order->refresh();
        $subscription->refresh();
        $listing->refresh();

        $this->assertSame('refunded', $payment->status);
        $this->assertSame('refunded', $order->status);
        $this->assertSame('suspended', $subscription->status);
        $this->assertSame('draft', $listing->status);
        $this->assertDatabaseHas('payment_refunds', [
            'payment_id' => $payment->id,
            'amount' => 500,
            'status' => 'processed',
        ]);
    }

    public function test_admin_can_extend_subscription_and_update_listing_expiry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Extension Listing',
            'slug' => 'extension-listing',
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
            'ends_at' => now()->addDays(10),
            'renews_at' => now()->addDays(10),
            'renewal_mode' => 'manual',
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $subscription->ends_at,
        ]);

        $oldEndsAt = $subscription->ends_at->copy();

        $this->actingAs($admin)->post(route('admin.finance.subscriptions.extend', $subscription), [
            'extension_days' => 30,
        ])->assertRedirect(route('admin.finance.index'));

        $subscription->refresh();
        $listing->refresh();

        $this->assertTrue($subscription->ends_at->gt($oldEndsAt));
        $this->assertEquals($subscription->ends_at->toDateTimeString(), $listing->package_expires_at->toDateTimeString());
    }

    public function test_admin_can_log_subscription_reminder(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => Listing::create([
                'user_id' => $owner->id,
                'title' => 'Reminder Listing',
                'slug' => 'reminder-listing',
                'status' => 'draft',
            ])->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(7),
            'renews_at' => now()->addDays(7),
            'renewal_mode' => 'manual',
        ]);

        $this->actingAs($admin)->post(route('admin.finance.subscriptions.reminder', $subscription), [
            'reminder_type' => 'expiry_notice',
            'channel' => 'email',
        ])->assertRedirect(route('admin.finance.index'));

        $this->assertDatabaseHas('subscription_reminders', [
            'subscription_id' => $subscription->id,
            'reminder_type' => 'expiry_notice',
            'channel' => 'email',
        ]);
    }
}
