<?php

namespace Tests\Feature;

use App\Mail\InvoiceIssuedMail;
use App\Models\Entitlement;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PayFastCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BusinessDirectoryRevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_page_lists_business_directory_packages(): void
    {
        $response = $this->get(route('checkout.index'));

        $response->assertOk();
        $response->assertSee('Business Directory Standard');
        $response->assertSee('Business Directory Self-Service');
    }

    public function test_paid_directory_payment_activates_listing_subscription_and_entitlement(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);

        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'New Business',
            'slug' => 'new-business',
            'status' => 'draft',
        ]);

        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();

        $order = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'ORD-1001',
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 500.00,
            'vat_amount' => 0,
            'total' => 500.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'package_id' => $package->id,
            'purchasable_type' => Listing::class,
            'purchasable_id' => $listing->id,
            'name_snapshot' => $package->name,
            'unit_price' => 500.00,
            'quantity' => 1,
            'billing_model' => 'six_monthly',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'LIFE-0001',
            'invoice_prefix_snapshot' => 'LIFE',
            'status' => 'draft',
            'currency' => 'ZAR',
            'subtotal' => 500.00,
            'vat_amount' => 0,
            'total' => 500.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'provider' => 'manual',
            'status' => 'pending',
            'amount' => 500.00,
            'currency' => 'ZAR',
        ]);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'provider_transaction_id' => 'txn-001',
        ]);

        $listing->refresh();
        $order->refresh();
        $invoice->refresh();

        $this->assertSame('published', $listing->status);
        $this->assertNotNull($listing->active_subscription_id);
        $this->assertNotNull($listing->package_expires_at);
        $this->assertSame('staff_assisted', $listing->source_channel);
        $this->assertSame('paid', $order->status);
        $this->assertSame('paid', $invoice->status);

        $subscription = Subscription::first();
        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status);

        $entitlement = Entitlement::first();
        $this->assertNotNull($entitlement);
        $this->assertSame('business_directory', $entitlement->entitlement_code);
        $this->assertSame('active', $entitlement->status);
    }

    public function test_authenticated_user_can_start_checkout_and_create_order_invoice_and_payment(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Checkout Listing',
            'slug' => 'checkout-listing',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('checkout.show', $order));
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'purchasable_type' => Listing::class,
            'purchasable_id' => $listing->id,
        ]);
        $this->assertDatabaseHas('invoices', [
            'order_id' => $order->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'payfast',
            'status' => 'pending',
        ]);
    }

    public function test_user_cannot_start_checkout_for_listing_they_do_not_manage(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $otherUser = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Protected Checkout Listing',
            'slug' => 'protected-checkout-listing',
            'status' => 'draft',
        ]);

        $this->actingAs($otherUser)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ])->assertForbidden();

        $this->assertDatabaseMissing('orders', [
            'user_id' => $otherUser->id,
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_inactive_package_cannot_start_checkout(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Inactive Package Listing',
            'slug' => 'inactive-package-listing',
            'status' => 'draft',
        ]);
        Package::where('slug', 'business-directory-standard-6m')->update([
            'status' => 'inactive',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ])->assertNotFound();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_package_without_current_price_cannot_start_checkout(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Expired Price Listing',
            'slug' => 'expired-price-listing',
            'status' => 'draft',
        ]);
        Package::where('slug', 'business-directory-standard-6m')
            ->firstOrFail()
            ->prices()
            ->update([
                'effective_to' => now()->subDay(),
            ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ])->assertSessionHasErrors('package_slug');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_callback_route_marks_payment_paid_and_activates_listing(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Callback Listing',
            'slug' => 'callback-listing',
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ]);

        $order = Order::firstOrFail();
        $signature = app(PayFastCheckoutService::class)->generateSignature([
            'order_number' => $order->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-123',
        ]);

        $response = $this->post(route('checkout.payfast.callback'), [
            'order_number' => $order->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-123',
            'signature' => $signature,
        ]);

        $response->assertOk();
        $listing->refresh();
        $this->assertSame('published', $listing->status);
        $this->assertTrue($listing->hasActiveBusinessEntitlement());
    }

    public function test_event_cannot_be_published_without_active_business_entitlement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Inactive Business',
            'slug' => 'inactive-business',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.events.store'), [
            'listing_id' => $listing->id,
            'title' => 'Blocked Event',
            'slug' => 'blocked-event',
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'category_ids' => [],
        ]);

        $response->assertSessionHasErrors('listing_id');
        $this->assertDatabaseMissing('events', [
            'slug' => 'blocked-event',
        ]);
    }

    public function test_payfast_initiation_logs_payment_attempt(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Initiation Listing',
            'slug' => 'initiation-listing',
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ]);

        $order = Order::firstOrFail();

        $response = $this->actingAs($owner)->post(route('checkout.payfast.initiate', $order));

        $response->assertRedirect(route('checkout.show', $order));
        $this->assertDatabaseHas('payment_attempts', [
            'payment_id' => $order->latestPayment()->id,
            'provider' => 'payfast',
            'status' => 'initiated',
        ]);
        $attempt = PaymentAttempt::firstOrFail();
        $this->assertNotEmpty($attempt->request_payload_json['signature'] ?? null);
    }

    public function test_invoice_send_marks_invoice_emailed_and_issued(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Invoice Listing',
            'slug' => 'invoice-listing',
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ]);

        $order = Order::firstOrFail();

        $this->actingAs($owner)->post(route('checkout.invoice.send', $order))
            ->assertRedirect(route('checkout.show', $order));

        $invoice = $order->invoices()->firstOrFail()->fresh();
        $this->assertSame('issued', $invoice->status);
        $this->assertNotNull($invoice->emailed_at);
        Mail::assertSent(InvoiceIssuedMail::class);
        $this->assertDatabaseHas('notification_logs', [
            'notification_type' => 'invoice_issued',
            'notifiable_type' => Invoice::class,
            'notifiable_id' => $invoice->id,
            'status' => 'sent',
        ]);
    }

    public function test_paid_event_package_activates_event_entitlement(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Entitled Business',
            'slug' => 'entitled-business',
            'status' => 'published',
        ]);

        $directoryPackage = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $directorySubscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $directoryPackage->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths(6),
            'renews_at' => now()->addMonths(6),
            'renewal_mode' => 'manual',
        ]);

        Entitlement::create([
            'subscription_id' => $directorySubscription->id,
            'entitled_type' => Listing::class,
            'entitled_id' => $listing->id,
            'entitlement_code' => 'business_directory',
            'active_from' => now(),
            'active_until' => now()->addMonths(6),
            'status' => 'active',
        ]);

        $listing->update([
            'active_subscription_id' => $directorySubscription->id,
            'package_expires_at' => now()->addMonths(6),
        ]);

        $event = Event::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Premium Event',
            'slug' => 'premium-event',
            'start_at' => now()->addWeek(),
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'event-one-off',
            'event_slug' => $event->slug,
        ]);

        $order = Order::latest('id')->firstOrFail();
        $signature = app(PayFastCheckoutService::class)->generateSignature([
            'order_number' => $order->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-event-123',
        ]);

        $this->post(route('checkout.payfast.callback'), [
            'order_number' => $order->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-event-123',
            'signature' => $signature,
        ])->assertOk();

        $event->refresh();
        $this->assertSame('published', $event->status);
        $this->assertNotNull($event->active_subscription_id);
        $this->assertNotNull($event->package_expires_at);
        $this->assertTrue($event->hasActiveEventEntitlement());
    }

    public function test_invalid_payfast_signature_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Bad Signature Listing',
            'slug' => 'bad-signature-listing',
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ]);

        $order = Order::firstOrFail();
        $response = $this->post(route('checkout.payfast.callback'), [
            'order_number' => $order->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-bad',
            'signature' => 'invalid-signature',
        ]);

        $response->assertStatus(422);
        $listing->refresh();
        $this->assertSame('draft', $listing->status);
    }

    public function test_payfast_callback_requires_signature(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Unsigned Callback Listing',
            'slug' => 'unsigned-callback-listing',
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ]);

        $order = Order::firstOrFail();
        $response = $this->post(route('checkout.payfast.callback'), [
            'order_number' => $order->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-unsigned',
        ]);

        $response->assertSessionHasErrors('signature');
        $listing->refresh();
        $this->assertSame('draft', $listing->status);
    }

    public function test_payfast_callback_rejects_amount_mismatch(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Amount Mismatch Listing',
            'slug' => 'amount-mismatch-listing',
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ]);

        $order = Order::firstOrFail();
        $payload = [
            'order_number' => $order->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-wrong-amount',
            'amount_gross' => '1.00',
            'currency' => 'ZAR',
        ];
        $payload['signature'] = app(PayFastCheckoutService::class)->generateSignature($payload);

        $this->post(route('checkout.payfast.callback'), $payload)
            ->assertStatus(422);

        $listing->refresh();
        $this->assertSame('draft', $listing->status);
    }

    public function test_payfast_callback_rejects_currency_mismatch(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Currency Mismatch Listing',
            'slug' => 'currency-mismatch-listing',
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ]);

        $order = Order::firstOrFail();
        $payload = [
            'order_number' => $order->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-wrong-currency',
            'amount_gross' => '500.00',
            'currency' => 'USD',
        ];
        $payload['signature'] = app(PayFastCheckoutService::class)->generateSignature($payload);

        $this->post(route('checkout.payfast.callback'), $payload)
            ->assertStatus(422);

        $listing->refresh();
        $this->assertSame('draft', $listing->status);
        $this->assertSame('pending', $order->latestPayment()->fresh()->status);
    }

    public function test_duplicate_success_callback_for_same_payment_is_idempotent(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Idempotent Callback Listing',
            'slug' => 'idempotent-callback-listing',
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ]);

        $order = Order::firstOrFail();
        $payload = [
            'order_number' => $order->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-idempotent',
            'amount_gross' => '500.00',
            'currency' => 'ZAR',
        ];
        $payload['signature'] = app(PayFastCheckoutService::class)->generateSignature($payload);

        $this->post(route('checkout.payfast.callback'), $payload)->assertOk();
        $this->post(route('checkout.payfast.callback'), $payload)->assertOk();

        $listing->refresh();
        $this->assertSame('published', $listing->status);
        $this->assertSame('paid', $order->latestPayment()->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('entitlements', 1);
    }

    public function test_payfast_callback_rejects_replayed_transaction_reference_for_another_payment(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $firstListing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Replay First Listing',
            'slug' => 'replay-first-listing',
            'status' => 'draft',
        ]);
        $secondListing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Replay Second Listing',
            'slug' => 'replay-second-listing',
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $firstListing->slug,
        ]);
        $firstOrder = Order::latest('id')->firstOrFail();
        $firstPayload = [
            'order_number' => $firstOrder->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-replayed',
            'amount_gross' => '500.00',
            'currency' => 'ZAR',
        ];
        $firstPayload['signature'] = app(PayFastCheckoutService::class)->generateSignature($firstPayload);
        $this->post(route('checkout.payfast.callback'), $firstPayload)->assertOk();

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $secondListing->slug,
        ]);
        $secondOrder = Order::latest('id')->firstOrFail();
        $secondPayload = [
            'order_number' => $secondOrder->order_number,
            'status' => 'paid',
            'provider_transaction_id' => 'pf-replayed',
            'amount_gross' => '500.00',
            'currency' => 'ZAR',
        ];
        $secondPayload['signature'] = app(PayFastCheckoutService::class)->generateSignature($secondPayload);

        $this->post(route('checkout.payfast.callback'), $secondPayload)
            ->assertStatus(409);

        $secondListing->refresh();
        $this->assertSame('draft', $secondListing->status);
    }

    public function test_failed_payment_can_be_retried_with_new_attempt(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Retry Listing',
            'slug' => 'retry-listing',
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->post(route('checkout.start'), [
            'package_slug' => 'business-directory-standard-6m',
            'listing_slug' => $listing->slug,
        ]);

        $order = Order::firstOrFail();
        $failedSignature = app(PayFastCheckoutService::class)->generateSignature([
            'order_number' => $order->order_number,
            'status' => 'failed',
            'provider_transaction_id' => 'pf-failed',
            'failure_reason' => 'Gateway timeout',
        ]);

        $this->post(route('checkout.payfast.callback'), [
            'order_number' => $order->order_number,
            'status' => 'failed',
            'provider_transaction_id' => 'pf-failed',
            'failure_reason' => 'Gateway timeout',
            'signature' => $failedSignature,
        ])->assertOk();

        $this->actingAs($owner)->post(route('checkout.payfast.retry', $order))
            ->assertRedirect(route('checkout.show', $order));

        $order->refresh();
        $this->assertSame('pending_payment', $order->status);
        $this->assertCount(2, $order->payments()->get());
        $this->assertSame('pending', $order->latestPayment()->status);
        $this->assertDatabaseHas('payment_attempts', [
            'payment_id' => $order->latestPayment()->id,
            'status' => 'initiated',
        ]);
    }

    public function test_admin_can_view_finance_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.finance.index'));

        $response->assertOk();
        $response->assertSee('Finance Dashboard');
    }
}
