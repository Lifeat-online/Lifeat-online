<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_owner_can_view_checkout_order(): void
    {
        $owner = User::factory()->create();
        $order = $this->orderFor($owner, 'ORD-POLICY-OWNER');
        Payment::create([
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'provider' => 'manual',
            'status' => 'pending',
            'amount' => 100,
            'currency' => 'ZAR',
        ]);

        $this->actingAs($owner)
            ->get(route('checkout.show', $order))
            ->assertOk();
    }

    public function test_unrelated_user_cannot_view_checkout_order(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $order = $this->orderFor($owner, 'ORD-POLICY-STRANGER');

        $this->actingAs($stranger)
            ->get(route('checkout.show', $order))
            ->assertForbidden();
    }

    public function test_unrelated_user_cannot_view_account_invoice(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $order = $this->orderFor($owner, 'ORD-POLICY-INVOICE');
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-POLICY-1',
            'invoice_prefix_snapshot' => 'LIFE',
            'status' => 'paid',
            'currency' => 'ZAR',
            'subtotal' => 100,
            'vat_amount' => 0,
            'total' => 100,
        ]);

        $this->actingAs($stranger)
            ->get(route('account.invoices.show', $invoice))
            ->assertForbidden();
    }

    private function orderFor(User $user, string $number): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => $number,
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 100,
            'vat_amount' => 0,
            'total' => 100,
        ]);
    }
}
