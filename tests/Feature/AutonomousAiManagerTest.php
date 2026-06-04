<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\AiManagerAction;
use App\Models\Article;
use App\Models\ArticleWordLedger;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\PackageType;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutonomousAiManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_ai_manager_with_revenue_allocation(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $writer = User::factory()->create(['role' => 'writer']);
        $package = $this->advertisingPackage();
        $order = $this->paidOrder($admin, $package, 1000);

        $article = Article::create([
            'user_id' => $writer->id,
            'title' => 'Human local article',
            'slug' => 'human-local-article',
            'excerpt' => 'A local story.',
            'body' => 'A paid human-written local story.',
            'status' => 'published',
            'submitted_at' => now(),
            'published_at' => now(),
        ]);

        ArticleWordLedger::create([
            'article_id' => $article->id,
            'writer_user_id' => $writer->id,
            'approved_by_user_id' => $admin->id,
            'word_count' => 200,
            'rate_per_word' => 1.00,
            'gross_amount' => 200,
            'status' => 'pending',
            'approved_at' => now(),
        ]);

        Setting::create([
            'key' => 'ai_manager.article_fund_percent',
            'value' => '35',
            'type' => 'number',
            'group' => 'ai_manager',
            'updated_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai-manager.index'))
            ->assertOk()
            ->assertSee('AI Manager')
            ->assertSee('Autonomy Policy')
            ->assertSee('Revenue Allocation')
            ->assertSee('R 350.00')
            ->assertSee('R 650.00')
            ->assertSee('R 200.00');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'paid',
            'amount' => 1000,
        ]);
    }

    public function test_admin_can_generate_recommendations_from_platform_signals(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $listing = Listing::factory()->create();

        AdCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $admin->id,
            'title' => 'Ready campaign',
            'slug' => 'ready-campaign',
            'headline' => 'Visit us today',
            'body' => 'A ready paid placement.',
            'destination_url' => 'https://example.test',
            'placement' => 'sidebar',
            'budget_amount' => 0,
            'budget_currency' => 'ZAR',
            'status' => 'ready',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.ai-manager.recommendations.store'))
            ->assertRedirect(route('admin.ai-manager.index'));

        $this->assertDatabaseHas('ai_manager_actions', [
            'domain' => 'advertising',
            'action_type' => 'review_ready_ads',
            'status' => AiManagerAction::STATUS_PROPOSED,
            'title' => 'Review 1 ready ad campaign',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai-manager.index'))
            ->assertOk()
            ->assertSee('Review 1 ready ad campaign')
            ->assertSee('Action Ledger');
    }

    public function test_admin_can_update_policy_and_review_action(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $action = AiManagerAction::create([
            'action_key' => hash('sha256', 'test-action'),
            'domain' => 'finance',
            'action_type' => 'review_writer_liability',
            'title' => 'Review writer liability',
            'summary' => 'Check the writer payment reserve.',
            'rationale' => 'Money movement requires human approval.',
            'risk_level' => 'medium',
            'required_mode' => AiManagerAction::MODE_APPROVAL,
            'impact_score' => 50,
            'confidence_score' => 90,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.ai-manager.policy.update'), [
                'mode' => AiManagerAction::MODE_APPROVAL,
                'article_fund_percent' => '40',
                'monthly_platform_ad_cap' => '2500',
                'max_actions_per_run' => '8',
                'allow_direct_marketing' => '1',
                'require_human_payout_approval' => '1',
            ])
            ->assertRedirect(route('admin.ai-manager.index'));

        $this->assertSame('approval', Setting::getValue('ai_manager.mode'));
        $this->assertSame('40', Setting::getValue('ai_manager.article_fund_percent'));
        $this->assertSame('2500', Setting::getValue('ai_manager.monthly_platform_ad_cap'));
        $this->assertSame('1', Setting::getValue('ai_manager.allow_direct_marketing'));

        $this->actingAs($admin)
            ->post(route('admin.ai-manager.actions.update', $action), [
                'status' => AiManagerAction::STATUS_APPROVED,
            ])
            ->assertRedirect(route('admin.ai-manager.index'));

        $this->assertDatabaseHas('ai_manager_actions', [
            'id' => $action->id,
            'status' => AiManagerAction::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
        ]);
    }

    private function advertisingPackage(): Package
    {
        $type = PackageType::query()->firstOrCreate(
            ['slug' => 'advert_package'],
            ['name' => 'Advert Package']
        );

        return Package::query()->create([
            'package_type_id' => $type->id,
            'name' => 'Sidebar Advert',
            'slug' => 'sidebar-advert-test',
            'description' => 'Paid advert package.',
            'billing_model' => 'once_off',
            'is_self_service' => true,
            'duration_days' => 30,
            'status' => 'active',
        ]);
    }

    private function paidOrder(User $user, Package $package, float $amount): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-AI-MANAGER-1',
            'status' => 'paid',
            'currency' => 'ZAR',
            'subtotal' => $amount,
            'vat_amount' => 0,
            'total' => $amount,
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'package_id' => $package->id,
            'name_snapshot' => $package->name,
            'unit_price' => $amount,
            'quantity' => 1,
            'billing_model' => 'once_off',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'provider' => 'manual',
            'status' => 'paid',
            'amount' => $amount,
            'currency' => 'ZAR',
            'provider_transaction_id' => 'AI-MANAGER-TEST',
            'paid_at' => now(),
        ]);

        return $order;
    }
}
