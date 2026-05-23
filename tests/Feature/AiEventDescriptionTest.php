<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiEventDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_event_description_for_listing_event(): void
    {
        $this->configureOpenRouter();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $listing = Listing::factory()->create([
            'title' => 'Bethlehem Market Hall',
            'city' => 'Bethlehem',
            'description' => 'A local venue for community markets and family events.',
        ]);
        $category = Category::create([
            'type' => 'event',
            'name' => 'Markets',
            'slug' => 'markets',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Bethlehem Winter Market',
                            'suggested_slug' => 'Bethlehem Winter Market!',
                            'excerpt' => 'A warm local market with food, crafts, and family stalls in Bethlehem.',
                            'description' => 'Join Bethlehem Market Hall for a winter community market with local food, crafts, and family-friendly stalls.',
                            'venue_name' => 'Bethlehem Market Hall',
                            'city' => 'Bethlehem',
                            'afrikaans_summary' => 'Kom geniet plaaslike kos, handwerk en gesinsvriendelike stalletjies.',
                            'missing_fields' => ['Ticket price'],
                            'follow_up_message' => 'Please confirm whether entry is free or ticketed.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ai.event-description'), [
                'listing_id' => $listing->id,
                'title' => 'Winter market',
                'venue_name' => 'Bethlehem Market Hall',
                'city' => 'Bethlehem',
                'start_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'rough_notes' => 'Food, crafts, family stalls, winter theme.',
                'category_ids' => [$category->id],
            ])
            ->assertOk()
            ->assertJsonPath('suggestion.title', 'Bethlehem Winter Market')
            ->assertJsonPath('suggestion.suggested_slug', 'bethlehem-winter-market')
            ->assertJsonPath('suggestion.missing_fields.0', 'Ticket price');

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'event_description',
            'provider' => 'openrouter',
            'model' => 'openai/gpt-oss-120b',
            'source_type' => Listing::class,
            'source_id' => $listing->id,
            'status' => AiGeneration::STATUS_DRAFT,
        ]);
    }

    public function test_listing_owner_can_generate_event_description_from_owner_workspace(): void
    {
        $this->configureOpenRouter();

        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Clarens Art Room',
            'city' => 'Clarens',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Clarens Family Paint Morning',
                            'suggested_slug' => 'clarens-family-paint-morning',
                            'excerpt' => 'A relaxed family paint morning at Clarens Art Room.',
                            'description' => 'Bring the family for a relaxed morning of painting at Clarens Art Room. All supplied details should be confirmed before publishing.',
                            'venue_name' => 'Clarens Art Room',
                            'city' => 'Clarens',
                            'afrikaans_summary' => 'n Ontspanne verfoggend vir gesinne in Clarens.',
                            'missing_fields' => ['Start time'],
                            'follow_up_message' => 'Please confirm the start time and booking details.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($owner)
            ->postJson(route('account.listings.ai.event-description', $listing), [
                'rough_notes' => 'Family paint morning, relaxed, supplies available, needs start time.',
                'city' => 'Clarens',
            ])
            ->assertOk()
            ->assertJsonPath('suggestion.excerpt', 'A relaxed family paint morning at Clarens Art Room.')
            ->assertJsonPath('suggestion.follow_up_message', 'Please confirm the start time and booking details.');

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'event_description',
            'provider' => 'openrouter',
            'source_type' => Listing::class,
            'source_id' => $listing->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_event_forms_expose_ai_event_writer(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($admin)
            ->get(route('admin.events.create'))
            ->assertOk()
            ->assertSee('AI Event Description Writer')
            ->assertSee(route('admin.ai.event-description'), false);

        $this->actingAs($owner)
            ->get(route('account.listings.events.create', $listing))
            ->assertOk()
            ->assertSee('AI Event Description Writer')
            ->assertSee(route('account.listings.ai.event-description', $listing), false);
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
