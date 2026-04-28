<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageType;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminCustomerLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_customers_by_contact_details(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $customer = User::factory()->create([
            'name' => 'Noluthando Mokoena',
            'email' => 'nolu@example.test',
            'phone' => '0820001234',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.customers.index', ['q' => 'nolu@example.test']));

        $response->assertOk();
        $response->assertSee('Customer Lookup');
        $response->assertSee('Noluthando Mokoena');
        $response->assertSee('nolu@example.test');
    }

    public function test_admin_customer_detail_shows_customer_journey_data(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $customer = User::factory()->create([
            'name' => 'Palesa Dube',
            'role' => 'business_owner',
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
            'title' => 'Dube Pharmacy',
            'slug' => 'dube-pharmacy',
            'city' => 'Bethlehem',
            'status' => 'draft',
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-1001',
            'status' => 'paid',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
            'placed_at' => now(),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'provider' => 'manual',
            'status' => 'paid',
            'amount' => 500,
            'currency' => 'ZAR',
            'provider_transaction_id' => 'TX-1001',
            'paid_at' => now(),
        ]);

        Subscription::create([
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths(6),
        ]);

        Article::create([
            'user_id' => $customer->id,
            'title' => 'Business Update',
            'slug' => 'business-update',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Customer Palesa Dube');
        $response->assertSee('ORD-1001');
        $response->assertSee('TX-1001');
        $response->assertSee('Dube Pharmacy');
        $response->assertSee('Business Update');
        $response->assertSee('Support Timeline');
        $response->assertSee('Extend');
        $response->assertSee('Log reminder');
        $response->assertSee('Record refund');
    }

    public function test_admin_can_add_support_note_to_customer_timeline(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $customer = User::factory()->create([
            'name' => 'Sipho Ndlovu',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.customers.notes.store', $customer), [
            'note' => 'Customer called about a failed payment retry and needs a follow-up tomorrow.',
        ]);

        $response->assertRedirect(route('admin.customers.show', $customer));

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'support.note_added',
            'subject_type' => User::class,
            'subject_id' => $customer->id,
        ]);

        $noteEntry = AuditLog::where('action', 'support.note_added')->firstOrFail();

        $this->assertSame(
            'Customer called about a failed payment retry and needs a follow-up tomorrow.',
            $noteEntry->after_json['note'] ?? null
        );

        $detailResponse = $this->actingAs($admin)->get(route('admin.customers.show', $customer));

        $detailResponse->assertOk();
        $detailResponse->assertSee('Support note added.');
        $detailResponse->assertSee('Customer called about a failed payment retry and needs a follow-up tomorrow.');
    }

    public function test_subscription_action_from_customer_page_redirects_back_to_customer_context(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $customer = User::factory()->create([
            'name' => 'Karabo Molefe',
            'role' => 'business_owner',
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
            'title' => 'Molefe Hardware',
            'slug' => 'molefe-hardware',
            'city' => 'Harrismith',
            'status' => 'draft',
        ]);

        $subscription = Subscription::create([
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(10),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.finance.subscriptions.reminder', $subscription), [
            'return_to' => route('admin.customers.show', $customer),
        ]);

        $response->assertRedirect(route('admin.customers.show', $customer));
        $this->assertDatabaseHas('subscription_reminders', [
            'subscription_id' => $subscription->id,
            'reminder_type' => 'expiry_notice',
            'channel' => 'email',
        ]);

        $detailResponse = $this->actingAs($admin)->get(route('admin.customers.show', $customer));

        $detailResponse->assertOk();
        $detailResponse->assertSee('Subscription reminder logged.');
        $detailResponse->assertSee('Subscription #'.$subscription->id);
        $detailResponse->assertSee('reminder_logged');
    }

    public function test_refund_action_from_customer_page_redirects_back_to_customer_context(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $customer = User::factory()->create([
            'name' => 'Refilwe Khumalo',
            'role' => 'business_owner',
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-2001',
            'status' => 'paid',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
            'placed_at' => now(),
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'provider' => 'manual',
            'status' => 'paid',
            'amount' => 500,
            'currency' => 'ZAR',
            'provider_transaction_id' => 'TX-2001',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.finance.payments.refunds.store', $payment), [
            'return_to' => route('admin.customers.show', $customer),
            'refund_amount' => 125.00,
            'refund_reason' => 'Customer requested a partial recovery after a duplicate charge.',
        ]);

        $response->assertRedirect(route('admin.customers.show', $customer));

        $this->assertDatabaseHas('payment_refunds', [
            'payment_id' => $payment->id,
            'processed_by_user_id' => $admin->id,
            'amount' => 125,
            'reason' => 'Customer requested a partial recovery after a duplicate charge.',
        ]);

        $detailResponse = $this->actingAs($admin)->get(route('admin.customers.show', $customer));

        $detailResponse->assertOk();
        $detailResponse->assertSee('Refund recorded.');
    }

    public function test_customer_timeline_filter_can_focus_on_support_notes(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $customer = User::factory()->create([
            'name' => 'Anele Zwane',
        ]);

        $recentNote = AuditLog::create([
            'actor_user_id' => $admin->id,
            'action' => 'support.note_added',
            'subject_type' => User::class,
            'subject_id' => $customer->id,
            'before_json' => [],
            'after_json' => ['note' => 'Needs callback regarding package renewal.'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        AuditLog::create([
            'actor_user_id' => $admin->id,
            'action' => 'payment.marked_paid',
            'subject_type' => User::class,
            'subject_id' => $customer->id,
            'before_json' => [],
            'after_json' => ['status' => 'paid'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', [
            $customer,
            'timeline_filter' => 'notes',
        ]));

        $response->assertOk();
        $response->assertSee('Needs callback regarding package renewal.');
        $response->assertDontSee('marked_paid');
    }

    public function test_customer_timeline_can_filter_by_action_text_and_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $customer = User::factory()->create([
            'name' => 'Timeline Filter Customer',
        ]);

        $recentNote = AuditLog::create([
            'actor_user_id' => $admin->id,
            'action' => 'support.note_added',
            'subject_type' => User::class,
            'subject_id' => $customer->id,
            'before_json' => [],
            'after_json' => ['note' => 'Recent support note inside the selected range.'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $oldNote = AuditLog::create([
            'actor_user_id' => $admin->id,
            'action' => 'support.note_added',
            'subject_type' => User::class,
            'subject_id' => $customer->id,
            'before_json' => [],
            'after_json' => ['note' => 'Old support note outside the selected range.'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        AuditLog::create([
            'actor_user_id' => $admin->id,
            'action' => 'payment.marked_paid',
            'subject_type' => User::class,
            'subject_id' => $customer->id,
            'before_json' => [],
            'after_json' => ['status' => 'paid'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        DB::table('audit_logs')->where('id', $recentNote->id)->update([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('audit_logs')->where('id', $oldNote->id)->update([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', [
            $customer,
            'timeline_action' => 'note',
            'timeline_from' => now()->subDays(2)->toDateString(),
            'timeline_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Recent support note inside the selected range.');
        $response->assertDontSee('Old support note outside the selected range.');
        $response->assertDontSee('marked_paid');
    }
}
