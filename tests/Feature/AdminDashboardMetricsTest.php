<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Article;
use App\Models\ArticleWordLedger;
use App\Models\Classified;
use App\Models\CivicFaultReport;
use App\Models\Listing;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageType;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\PushCampaign;
use App\Models\StaffWallet;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WriterApplication;
use App\Models\WriterPaymentBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_metrics_json(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.metrics'));

        $response->assertOk();
        $response->assertJsonStructure([
            'faults' => ['pending', 'approved', 'reported', 'in_progress', 'resolved', 'reported_last_hour', 'resolved_last_7d', 'avg_resolution_hours_last_50'],
            'councillors' => ['active', 'inactive'],
            'advertising' => ['ads_active', 'ads_ready', 'push_pending'],
            'integrations' => ['total', 'active'],
            'core' => ['listings', 'vouchers'],
            'kpis' => [
                'revenue' => ['currency', 'paid_total', 'paid_total_display', 'paid_last_30d', 'paid_last_30d_display', 'paid_today', 'paid_today_display'],
                'listings' => ['total', 'active', 'draft', 'published_without_active_subscription'],
                'subscriptions' => ['active', 'expiring_7d', 'expired_pending_sweep'],
                'payments' => ['failed', 'failed_last_24h', 'pending_orders', 'stale_pending_orders'],
                'approval_queues' => ['writer_applications', 'ad_campaigns', 'push_campaigns', 'articles', 'article_briefs', 'classifieds', 'civic_faults', 'notifications_attention', 'total'],
                'writer_payouts' => ['pending_ledgers', 'pending_amount', 'pending_amount_display', 'exported_batches', 'exported_amount', 'exported_amount_display'],
                'staff_wallets' => ['wallets', 'available_liability', 'available_liability_display', 'pending_liability', 'pending_liability_display', 'active_payout_requests', 'active_payout_amount', 'active_payout_amount_display'],
            ],
        ]);
    }

    public function test_metrics_json_tracks_operational_kpis(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $writer = User::factory()->create(['role' => 'writer']);
        $staff = User::factory()->create(['role' => 'sales_staff']);
        $listing = Listing::factory()->create(['user_id' => $admin->id, 'status' => 'published']);
        Listing::factory()->create(['user_id' => $admin->id, 'status' => 'published']);

        $type = PackageType::firstOrCreate(
            ['slug' => 'business_directory'],
            ['name' => 'Directory']
        );
        $package = Package::create([
            'package_type_id' => $type->id,
            'name' => 'Directory Listing',
            'slug' => 'directory-listing',
            'billing_model' => 'six_monthly',
            'duration_days' => 180,
            'status' => 'active',
        ]);
        $subscription = Subscription::create([
            'user_id' => $admin->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(3),
        ]);
        $listing->update(['active_subscription_id' => $subscription->id]);

        $paidOrder = Order::create([
            'user_id' => $admin->id,
            'order_number' => 'ORD-KPI-PAID',
            'status' => 'paid',
            'currency' => 'ZAR',
            'subtotal' => 125,
            'vat_amount' => 0,
            'total' => 125,
            'placed_at' => now(),
        ]);
        Payment::create([
            'order_id' => $paidOrder->id,
            'user_id' => $admin->id,
            'provider' => 'payfast',
            'status' => 'paid',
            'amount' => 125,
            'currency' => 'ZAR',
            'paid_at' => now(),
        ]);

        $failedOrder = Order::create([
            'user_id' => $admin->id,
            'order_number' => 'ORD-KPI-FAILED',
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 50,
            'vat_amount' => 0,
            'total' => 50,
            'placed_at' => now()->subDays(2),
        ]);
        $failedOrder->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->save();
        Payment::create([
            'order_id' => $failedOrder->id,
            'user_id' => $admin->id,
            'provider' => 'payfast',
            'status' => 'failed',
            'amount' => 50,
            'currency' => 'ZAR',
            'failure_reason' => 'Fixture failure',
        ]);

        AdCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $admin->id,
            'title' => 'Ready advert',
            'slug' => 'ready-advert',
            'status' => 'ready',
        ]);
        PushCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $admin->id,
            'title' => 'Ready push',
            'slug' => 'ready-push',
            'message' => 'Push message',
            'status' => 'ready',
        ]);
        Article::create([
            'user_id' => $writer->id,
            'title' => 'Pending article',
            'slug' => 'pending-article',
            'body' => 'Pending article body.',
            'status' => 'pending_review',
        ]);
        Classified::create([
            'user_id' => $admin->id,
            'title' => 'Pending classified',
            'slug' => 'pending-classified',
            'status' => Classified::STATUS_PENDING,
        ]);
        CivicFaultReport::create([
            'reporter_user_id' => $admin->id,
            'category' => 'pothole',
            'severity' => CivicFaultReport::SEVERITY_MEDIUM,
            'description' => 'A report awaiting moderation.',
            'address_label' => 'Main Road',
            'latitude' => -28.2300000,
            'longitude' => 28.3100000,
            'status' => CivicFaultReport::STATUS_REPORTED,
            'consented_at' => now(),
            'is_approved' => false,
        ]);
        WriterApplication::create([
            'first_name' => 'Pending',
            'last_name' => 'Writer',
            'email' => 'pending-writer@example.com',
            'phone' => '0820000000',
            'username' => 'pending-writer',
            'profile_bio' => str_repeat('Writer bio. ', 8),
            'sample_article_title' => 'Sample article',
            'sample_article_body' => str_repeat('Sample article body. ', 20),
            'sample_advert_title' => 'Sample advert',
            'sample_advert_body' => str_repeat('Sample advert body. ', 20),
            'status' => WriterApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
        NotificationLog::create([
            'channel' => 'email',
            'notification_type' => 'kpi_fixture',
            'recipient' => 'ops@example.com',
            'status' => 'failed',
            'sent_at' => now(),
        ]);

        $article = Article::create([
            'user_id' => $writer->id,
            'title' => 'Ledger article',
            'slug' => 'ledger-article',
            'body' => 'Approved writer article body.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        ArticleWordLedger::create([
            'article_id' => $article->id,
            'writer_user_id' => $writer->id,
            'approved_by_user_id' => $admin->id,
            'word_count' => 100,
            'rate_per_word' => 1.5,
            'gross_amount' => 150,
            'status' => 'pending',
            'approved_at' => now(),
        ]);
        WriterPaymentBatch::create([
            'reference' => 'WPB-KPI',
            'created_by_user_id' => $admin->id,
            'status' => 'exported',
            'item_count' => 1,
            'gross_amount' => 75,
            'exported_at' => now(),
        ]);
        $wallet = StaffWallet::create([
            'user_id' => $staff->id,
            'currency' => 'ZAR',
            'available_balance' => 300,
            'pending_balance' => 25,
            'paid_out_total' => 0,
        ]);
        PayoutRequest::create([
            'wallet_id' => $wallet->id,
            'requested_by_user_id' => $staff->id,
            'amount' => 120,
            'currency' => 'ZAR',
            'status' => PayoutRequest::STATUS_REQUESTED,
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.metrics'));

        $response
            ->assertOk()
            ->assertJsonPath('kpis.revenue.paid_total', 125)
            ->assertJsonPath('kpis.revenue.paid_total_display', 'R 125.00')
            ->assertJsonPath('kpis.listings.active', 1)
            ->assertJsonPath('kpis.listings.published_without_active_subscription', 1)
            ->assertJsonPath('kpis.subscriptions.expiring_7d', 1)
            ->assertJsonPath('kpis.payments.failed', 1)
            ->assertJsonPath('kpis.payments.stale_pending_orders', 1)
            ->assertJsonPath('kpis.approval_queues.total', 7)
            ->assertJsonPath('kpis.writer_payouts.pending_amount_display', 'R 150.00')
            ->assertJsonPath('kpis.writer_payouts.exported_amount_display', 'R 75.00')
            ->assertJsonPath('kpis.staff_wallets.available_liability_display', 'R 300.00')
            ->assertJsonPath('kpis.staff_wallets.active_payout_requests', 1)
            ->assertJsonPath('kpis.staff_wallets.active_payout_amount_display', 'R 120.00');
    }

    public function test_admin_dashboard_renders_operational_kpi_cards(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Operational KPIs')
            ->assertSee('Paid Revenue')
            ->assertSee('Active Listings')
            ->assertSee('Staff Wallet Liability');
    }

    public function test_support_user_cannot_access_listing_management_routes(): void
    {
        $support = User::factory()->create([
            'role' => 'support',
        ]);

        $this->actingAs($support)->get(route('admin.listings.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.events.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.articles.index'))->assertForbidden();
    }
}
