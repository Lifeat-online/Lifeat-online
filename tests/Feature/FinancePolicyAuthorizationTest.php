<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_can_view_but_not_export_finance_data(): void
    {
        $support = User::factory()->create(['role' => 'support']);

        $this->actingAs($support)
            ->get(route('admin.finance.index'))
            ->assertOk();

        $this->actingAs($support)
            ->get(route('admin.finance.export', 'payments'))
            ->assertForbidden();
    }

    public function test_staff_cannot_view_finance_dashboard(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.finance.index'))
            ->assertForbidden();
    }

    public function test_editor_can_reconcile_payment_but_cannot_refund(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $payment = $this->paymentFor(User::factory()->create());

        $this->actingAs($editor)
            ->post(route('admin.finance.payments.mark-paid', $payment))
            ->assertRedirect(route('admin.finance.index'));

        $this->actingAs($editor)
            ->post(route('admin.finance.payments.refunds.store', $payment), [
                'refund_amount' => 10,
                'refund_reason' => 'Not permitted.',
            ])
            ->assertForbidden();
    }

    public function test_editor_can_extend_subscription_but_support_cannot(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $support = User::factory()->create(['role' => 'support']);
        $subscription = $this->subscriptionFor(User::factory()->create());

        $this->actingAs($editor)
            ->post(route('admin.finance.subscriptions.extend', $subscription), [
                'extension_days' => 7,
            ])
            ->assertRedirect(route('admin.finance.index'));

        $this->actingAs($support)
            ->post(route('admin.finance.subscriptions.extend', $subscription), [
                'extension_days' => 7,
            ])
            ->assertForbidden();
    }

    private function paymentFor(User $user): Payment
    {
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-FIN-POLICY-'.$user->id,
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 100,
            'vat_amount' => 0,
            'total' => 100,
        ]);

        return Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'provider' => 'manual',
            'status' => 'pending',
            'amount' => 100,
            'currency' => 'ZAR',
        ]);
    }

    private function subscriptionFor(User $user): Subscription
    {
        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();

        return Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(14),
            'renews_at' => now()->addDays(14),
            'renewal_mode' => 'manual',
        ]);
    }
}
