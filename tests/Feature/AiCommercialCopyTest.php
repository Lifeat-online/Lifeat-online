<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCommercialCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_ad_copy_for_listing_campaign(): void
    {
        $this->configureOpenRouter();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $listing = Listing::factory()->create([
            'title' => 'Bethlehem Tyres',
            'city' => 'Bethlehem',
            'description' => 'Tyre fitment and wheel alignment for local drivers.',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'campaign_title' => 'Bethlehem Tyres Winter Safety',
                            'headline' => 'Get road-ready in Bethlehem',
                            'body' => 'Book tyre fitment and wheel alignment before your next trip.',
                            'call_to_action' => 'Book now',
                            'afrikaans_summary' => 'Maak jou motor reg vir die pad in Bethlehem.',
                            'missing_fields' => [],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ai.ad-copy'), [
                'listing_id' => $listing->id,
                'rough_notes' => 'Winter tyre safety campaign.',
                'placement' => 'banner',
            ])
            ->assertOk()
            ->assertJsonPath('suggestion.headline', 'Get road-ready in Bethlehem')
            ->assertJsonPath('suggestion.call_to_action', 'Book now');

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'ad_copy',
            'provider' => 'openrouter',
            'status' => AiGeneration::STATUS_DRAFT,
        ]);
    }

    public function test_listing_owner_can_generate_push_copy(): void
    {
        $this->configureOpenRouter();

        $owner = User::factory()->create();
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Clarens Coffee',
            'city' => 'Clarens',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'campaign_title' => 'Clarens Coffee Weekend Push',
                            'headline' => 'Fresh coffee in Clarens',
                            'message' => 'Pop in this weekend for fresh coffee and local baked treats.',
                            'options' => [],
                            'afrikaans_option' => [
                                'title' => 'Vars koffie in Clarens',
                                'body' => 'Kom loer in vir koffie en plaaslike lekkernye.',
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($owner)
            ->postJson(route('account.listings.ai.push-copy', $listing), [
                'rough_notes' => 'Weekend coffee and baked treats.',
                'audience_scope' => 'listing_city',
            ])
            ->assertOk()
            ->assertJsonPath('suggestion.message', 'Pop in this weekend for fresh coffee and local baked treats.');

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'push_copy',
            'provider' => 'openrouter',
        ]);
    }

    public function test_listing_owner_can_generate_voucher_copy(): void
    {
        $this->configureOpenRouter();

        $owner = User::factory()->create();
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Reitz Hardware',
            'city' => 'Reitz',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => '10% Off Plumbing Supplies',
                            'description' => 'Save on selected plumbing supplies at Reitz Hardware.',
                            'terms' => 'Valid while stock lasts. Cannot be exchanged for cash.',
                            'redemption_instructions' => 'Show this voucher in-store before payment.',
                            'afrikaans_summary' => 'Spaar op geselekteerde loodgieterbenodigdhede.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($owner)
            ->postJson(route('account.listings.ai.voucher-copy', $listing), [
                'rough_notes' => '10 percent off plumbing supplies, while stock lasts.',
                'voucher_type' => 'discount_percent',
                'discount_percent' => 10,
                'usage_limit' => 50,
            ])
            ->assertOk()
            ->assertJsonPath('suggestion.title', '10% Off Plumbing Supplies')
            ->assertJsonPath('suggestion.redemption_instructions', 'Show this voucher in-store before payment.');

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'voucher_copy',
            'provider' => 'openrouter',
        ]);
    }

    private function configureOpenRouter(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => 'sk-or-test',
            'services.ai.providers.openrouter.model' => 'openai/gpt-oss-120b',
        ]);
    }
}
