<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Entitlement;
use App\Models\Invoice;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PushCampaign;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Services\PayFastCheckoutService;
use App\Services\PushCampaignDispatchService;
use App\Services\SubscriptionLifecycleService;
use App\Services\WebPushDeliveryService;
use App\Support\Logging\OperationalLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;

class OperationalStructuredLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_logger_redacts_sensitive_context(): void
    {
        Log::spy();

        OperationalLog::info('payment.callback_received', [
            'signature' => 'raw-signature',
            'nested' => [
                'api_token' => 'raw-token',
                'safe_id' => 123,
            ],
        ]);

        $this->assertInfoLogged('payment.callback_received', fn (array $context): bool =>
            $context['event'] === 'payment.callback_received'
            && $context['domain'] === 'payment'
            && $context['signature'] === '[redacted]'
            && $context['nested']['api_token'] === '[redacted]'
            && $context['nested']['safe_id'] === 123
        );
    }

    public function test_payfast_callback_emits_structured_payment_logs(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Logged Callback Listing',
            'slug' => 'logged-callback-listing',
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
            'provider_transaction_id' => 'pf-structured-log',
            'amount_gross' => number_format((float) $order->total, 2, '.', ''),
            'currency' => $order->currency,
        ];
        $payload['signature'] = app(PayFastCheckoutService::class)->generateSignature($payload);

        Log::spy();

        $this->post(route('checkout.payfast.callback'), $payload)->assertOk();

        $payment = $order->latestPayment()->fresh();

        $this->assertInfoLogged('payment.callback_paid', fn (array $context): bool =>
            $context['order_id'] === $order->id
            && $context['payment_id'] === $payment->id
            && $context['payment_status'] === 'paid'
            && $context['provider_transaction_id'] === 'pf-structured-log'
            && ! array_key_exists('signature', $context)
        );

        $this->assertInfoLogged('payment.status_changed', fn (array $context): bool =>
            $context['payment_id'] === $payment->id
            && $context['status'] === 'paid'
            && $context['provider'] === 'payfast'
        );
    }

    public function test_subscription_lifecycle_and_finance_actions_emit_structured_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Structured Finance Listing',
            'slug' => 'structured-finance-listing',
            'status' => 'published',
        ]);
        $subscription = $this->subscriptionForListing($owner, $listing);
        $payment = $this->paymentForListingOrder($owner, $listing);

        Log::spy();

        app(SubscriptionLifecycleService::class)->extend($subscription, 14);

        $this->actingAs($admin)->post(route('admin.finance.payments.mark-paid', $payment))
            ->assertRedirect(route('admin.finance.index'));

        $this->assertInfoLogged('subscription.extended', fn (array $context): bool =>
            $context['subscription_id'] === $subscription->id
            && $context['extension_days'] === 14
            && $context['status'] === 'active'
        );

        $this->assertInfoLogged('finance.action_recorded', fn (array $context): bool =>
            $context['action'] === 'payment.marked_paid'
            && $context['actor_user_id'] === $admin->id
            && $context['subject_type'] === Payment::class
            && $context['subject_id'] === $payment->id
        );
    }

    public function test_voucher_claim_and_consume_emit_structured_logs(): void
    {
        Mail::fake();

        $customer = User::factory()->create();
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'published',
        ]);
        $voucher = Voucher::factory()->create([
            'listing_id' => $listing->id,
            'status' => 'published',
            'start_at' => now()->subHour(),
            'end_at' => now()->addDay(),
            'usage_limit' => 2,
            'redemptions_count' => 0,
        ]);

        Log::spy();

        $this->actingAs($customer)->post(route('vouchers.redeem', [$listing, $voucher]))
            ->assertRedirect(route('account.vouchers.index'));

        $redemption = VoucherRedemption::firstOrFail();

        $this->actingAs($owner)->post(route('staff.vouchers.consume'), [
            'code' => $redemption->code,
        ])->assertRedirect(route('staff.vouchers.redeem', ['code' => $redemption->code]));

        $this->assertInfoLogged('voucher.claimed', fn (array $context): bool =>
            $context['voucher_id'] === $voucher->id
            && $context['listing_id'] === $listing->id
            && $context['customer_user_id'] === $customer->id
            && $context['voucher_redemption_id'] === $redemption->id
        );

        $this->assertInfoLogged('voucher.consumed', fn (array $context): bool =>
            $context['voucher_id'] === $voucher->id
            && $context['voucher_redemption_id'] === $redemption->id
            && $context['staff_user_id'] === $owner->id
            && $context['code_hash'] === OperationalLog::hashValue($redemption->code)
        );
    }

    public function test_push_campaign_dispatch_emits_structured_delivery_log(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'published',
        ]);
        $this->attachListingSubscription($owner, $listing);

        $campaign = PushCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Structured Push',
            'slug' => 'structured-push',
            'headline' => 'Local update',
            'message' => 'Campaign message',
            'schedule_at' => now()->subMinute(),
            'audience_scope' => 'listing_city',
            'status' => 'active',
            'budget_currency' => 'ZAR',
        ]);
        $this->attachCampaignSubscription($campaign, 'push-campaign-once');

        $this->mock(WebPushDeliveryService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendCampaign')->once()->andReturn([
                'configured' => true,
                'attempted' => 3,
                'sent' => 2,
                'failed' => 1,
                'expired' => 0,
            ]);
        });

        Log::spy();

        $notification = app(PushCampaignDispatchService::class)->dispatch($campaign);

        $this->assertInfoLogged('campaign.push_dispatched', fn (array $context): bool =>
            $context['push_campaign_id'] === $campaign->id
            && $context['listing_id'] === $listing->id
            && $context['notification_log_id'] === $notification->id
            && $context['web_push_attempted'] === 3
            && $context['web_push_sent'] === 2
            && $context['web_push_failed'] === 1
        );
    }

    private function assertInfoLogged(string $event, callable $predicate): void
    {
        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool =>
                $message === 'lifeat.operational.'.$event && $predicate($context)
            )
            ->atLeast()
            ->once();
    }

    private function subscriptionForListing(User $owner, Listing $listing): Subscription
    {
        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();

        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'renews_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ]);

        Entitlement::create([
            'subscription_id' => $subscription->id,
            'entitled_type' => Listing::class,
            'entitled_id' => $listing->id,
            'entitlement_code' => 'business_directory',
            'active_from' => now()->subDay(),
            'active_until' => now()->addMonth(),
            'status' => 'active',
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $subscription->ends_at,
        ]);

        return $subscription;
    }

    private function attachListingSubscription(User $owner, Listing $listing): Subscription
    {
        return $this->subscriptionForListing($owner, $listing);
    }

    private function attachCampaignSubscription(PushCampaign $campaign, string $packageSlug): Subscription
    {
        $package = Package::where('slug', $packageSlug)->firstOrFail();
        $subscription = Subscription::create([
            'user_id' => $campaign->user_id,
            'package_id' => $package->id,
            'subscribable_type' => PushCampaign::class,
            'subscribable_id' => $campaign->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'renews_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ]);

        Entitlement::create([
            'subscription_id' => $subscription->id,
            'entitled_type' => PushCampaign::class,
            'entitled_id' => $campaign->id,
            'entitlement_code' => $package->entitlementCode(),
            'active_from' => now()->subDay(),
            'active_until' => now()->addMonth(),
            'status' => 'active',
        ]);

        $campaign->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $subscription->ends_at,
        ]);

        return $subscription;
    }

    private function paymentForListingOrder(User $owner, Listing $listing): Payment
    {
        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $order = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'ORD-STRUCTURED-LOG',
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

        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-STRUCTURED-LOG',
            'invoice_prefix_snapshot' => 'LIFE',
            'status' => 'draft',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
        ]);

        return Payment::create([
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'provider' => 'manual',
            'status' => 'pending',
            'amount' => 500,
            'currency' => 'ZAR',
        ]);
    }
}
