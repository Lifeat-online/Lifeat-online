<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\Classified;
use App\Models\Listing;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AskLifeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ask_life_guides_business_directory_onboarding_without_ai_provider(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        $this->actingAs($this->devOwner())->postJson(route('ask-life.store'), [
            'question' => 'Can you assist me adding my business to your directory?',
        ])
            ->assertOk()
            ->assertJsonPath('source', 'guided')
            ->assertJsonPath('sources.0.id', 'guide:add-listing')
            ->assertJsonPath('sources.0.url', route('add-listing.index'))
            ->assertJsonPath('sources.0.actions.0.label', 'View')
            ->assertJsonPath('sources.1.id', 'guide:advertise')
            ->assertJsonPath('search_url', null)
            ->assertSee('Start on Add Listing', false);

        Http::assertNothingSent();
    }

    public function test_ask_life_returns_local_sources_without_ai_provider(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        Article::create([
            'title' => 'Bethlehem Water Repairs',
            'slug' => 'bethlehem-water-repairs',
            'excerpt' => 'Municipal teams are repairing water lines in Bethlehem this week.',
            'body' => 'Residents in Bethlehem should prepare for water repair work.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($this->devOwner())->postJson(route('ask-life.store'), [
            'question' => 'What is happening with water in Bethlehem?',
        ])
            ->assertOk()
            ->assertJsonPath('source', 'fallback')
            ->assertJsonPath('sources.0.type', 'article')
            ->assertJsonPath('sources.0.title', 'Bethlehem Water Repairs')
            ->assertJsonPath('answer_actions.0.label', 'Open best match');

        Http::assertNothingSent();
    }

    public function test_ask_life_uses_page_context_for_listing_workspace_help_without_ai_provider(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        $user = $this->devOwner();

        $this->actingAs($user)
            ->postJson(route('ask-life.store'), [
                'question' => 'What should I do next with my listing?',
                'context' => [
                    'page_type' => 'account_listing_workspace',
                    'page_title' => 'Account listing workspace',
                    'page_heading' => 'Phoenix Tyre Workshop',
                    'page_url' => 'https://life.test/account/listings/12',
                    'path' => '/account/listings/12',
                    'timezone' => 'Africa/Johannesburg',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('intent.key', 'business_owner')
            ->assertJsonPath('sources.0.id', 'page:current')
            ->assertJsonFragment(['label' => 'My listings'])
            ->assertJsonFragment(['id' => 'guide:business-owner']);

        Http::assertNothingSent();
    }

    public function test_ask_life_never_surfaces_signed_in_users_own_draft_listing(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        $user = $this->devOwner();
        $listing = Listing::factory()
            ->create([
                'user_id' => $user->id,
                'title' => 'Phoenix Tyre Workshop',
                'excerpt' => 'Tyre repairs, puncture fixes, wheel alignment, and vehicle checks.',
                'description' => 'Automotive repair workshop in Bethlehem.',
                'city' => 'Bethlehem',
                'region' => 'Free State',
                'status' => 'draft',
            ]);

        $this->actingAs($user)
            ->postJson(route('ask-life.store'), [
                'question' => 'tyre repair in Bethlehem',
            ])
            ->assertOk()
            ->assertJsonMissing(['id' => 'listing:'.$listing->id]);

        Http::assertNothingSent();
    }

    public function test_ask_life_uses_ai_when_configured_and_logs_generation(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => 'sk-or-test',
            'services.ai.providers.openrouter.model' => 'openai/gpt-oss-120b',
        ]);

        $classified = Classified::create([
            'title' => 'Mahindra Bakkie For Sale',
            'slug' => 'mahindra-bakkie-for-sale',
            'description' => 'A 2012 Mahindra bakkie in Bethlehem, needs minor work.',
            'price' => 55000,
            'currency' => 'ZAR',
            'city' => 'Bethlehem',
            'status' => Classified::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'answer' => 'There is a Mahindra bakkie classified in Bethlehem listed for ZAR 55,000.00.',
                                'confidence' => 0.82,
                                'source_ids' => ['classified:'.$classified->id],
                                'follow_up_questions' => ['Do you want classifieds only?'],
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($this->devOwner())->postJson(route('ask-life.store'), [
            'question' => 'Mahindra bakkie in Bethlehem',
        ])
            ->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('answer', 'There is a Mahindra bakkie classified in Bethlehem listed for ZAR 55,000.00.')
            ->assertJsonPath('sources.0.id', 'classified:'.$classified->id);

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'ask_life',
            'provider' => 'openrouter',
            'model' => 'openai/gpt-oss-120b',
            'status' => AiGeneration::STATUS_DRAFT,
        ]);
    }

    public function test_ask_life_uses_page_locale_for_ai_answers_even_when_question_is_english(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => 'sk-or-test',
            'services.ai.providers.openrouter.model' => 'openai/gpt-oss-120b',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'answer' => 'Ek is Ask Life. Ek kan jou help om die regte Life@ afdeling te vind.',
                                'confidence' => 0.74,
                                'source_ids' => ['guide:search'],
                                'follow_up_questions' => ['In watter dorp moet ek soek?'],
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($this->devOwner())->postJson(route('ask-life.store'), [
            'question' => 'Jimmy, what can you help me with?',
            'context' => [
                'locale' => 'af',
                'page_type' => 'general',
                'timezone' => 'Africa/Johannesburg',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('locale', 'af')
            ->assertJsonPath('answer', 'Ek is Ask Life. Ek kan jou help om die regte Life@ afdeling te vind.')
            ->assertJsonPath('sources.0.id', 'guide:search')
            ->assertJsonPath('sources.0.label', 'Gids')
            ->assertJsonPath('sources.0.title', 'Ask Life kan jou help om Life@ te gebruik')
            ->assertJsonPath('answer_actions.0.label', 'Maak beste passing oop')
            ->assertJsonPath('answer_actions.1.label', 'Volledige soektog');

        Http::assertSent(function ($request): bool {
            $payload = json_decode($request->body(), true);
            $input = json_decode((string) data_get($payload, 'messages.1.content'), true);

            return data_get($input, 'target_locale') === 'af'
                && data_get($input, 'target_language') === 'Afrikaans'
                && str_contains((string) data_get($input, 'language_instruction'), 'Use the product name Ask Life')
                && str_contains((string) data_get($payload, 'messages.0.content'), 'Use the product name Ask Life');
        });

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'ask_life',
            'prompt_version' => 'ask_life_v9',
            'output_language' => 'af',
        ]);
    }

    public function test_ask_life_fallback_guides_respect_afrikaans_page_locale_without_ai_provider(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        $this->actingAs($this->devOwner())->postJson(route('ask-life.store'), [
            'question' => 'Jimmy, what can you help me with?',
            'context' => [
                'locale' => 'af',
                'page_type' => 'general',
                'timezone' => 'Africa/Johannesburg',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('source', 'fallback')
            ->assertJsonPath('locale', 'af')
            ->assertJsonPath('sources.0.id', 'guide:search')
            ->assertJsonPath('sources.0.label', 'Gids')
            ->assertJsonPath('sources.0.title', 'Ask Life kan jou help om Life@ te gebruik')
            ->assertJsonPath('answer_actions.0.label', 'Maak beste passing oop')
            ->assertJsonPath('answer_actions.1.label', 'Volledige soektog')
            ->assertSee('Ek is Ask Life. Ek kan jou help uitwerk waarheen om op Life@ te gaan', false);

        Http::assertNothingSent();
    }

    public function test_ask_life_widget_renders_saved_afrikaans_locale_labels_before_javascript_runs(): void
    {
        $dev = $this->devOwner();

        $response = $this
            ->actingAs($dev)
            ->withSession(['locale' => 'af'])
            ->get(route('home'));

        $response->assertOk();
        $response->assertSee('data-locale="af"', false);
        $response->assertSee('Vra vir Ask Life', false);
        $response->assertSee('Vind plaaslike antwoorde, aksies en die regte Life@ bladsy.', false);
        $response->assertSee('Hallo, ek is Ask Life. Waarmee moet ek jou help?', false);
        $response->assertSee('Probeer: bandherstelwerk in Bethlehem', false);
        $response->assertDontSee('Vra vir Jakobus', false);
        $response->assertDontSee('Hallo, ek is Jakobus', false);
    }

    public function test_ask_life_is_hidden_and_blocked_for_non_dev_users(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-ask-life', false);

        $this->postJson(route('ask-life.store'), [
            'question' => 'Can Jimmy help me?',
        ])->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->postJson(route('ask-life.store'), [
                'question' => 'Can Jimmy help me?',
            ])->assertForbidden();
    }

    public function test_ask_life_recommends_listed_developers_for_website_needs(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        $dev = $this->devOwner();
        $listing = Listing::factory()->create([
            'title' => 'Pixel Forge Developers',
            'excerpt' => 'Website design, web apps, online stores, and digital support.',
            'description' => 'Local developers building websites, software, and ecommerce sites.',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'status' => 'published',
            'published_at' => now(),
            'is_featured' => true,
        ]);
        $this->activateListing($listing);

        $this->actingAs($dev)
            ->postJson(route('ask-life.store'), [
                'question' => 'I need a website for my business in Bethlehem',
            ])
            ->assertOk()
            ->assertJsonPath('source', 'guided')
            ->assertJsonPath('intent.key', 'website_project')
            ->assertJsonPath('sources.0.id', 'listing:'.$listing->id)
            ->assertJsonPath('sources.0.title', 'Pixel Forge Developers')
            ->assertSee('listed developers', false);

        Http::assertNothingSent();
    }

    public function test_ask_life_recommends_accommodation_for_short_term_stays(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        $dev = $this->devOwner();
        $listing = Listing::factory()->create([
            'title' => 'Clarens Mountain B&B',
            'excerpt' => 'Short-term accommodation, guest rooms, and weekend stays.',
            'description' => 'Bed and breakfast guest house for overnight and short-term stays in Clarens.',
            'city' => 'Clarens',
            'region' => 'Free State',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->activateListing($listing);

        $this->actingAs($dev)
            ->postJson(route('ask-life.store'), [
                'question' => 'I need a place to stay short term in Clarens',
            ])
            ->assertOk()
            ->assertJsonPath('source', 'guided')
            ->assertJsonPath('intent.key', 'accommodation_search')
            ->assertJsonPath('sources.0.id', 'listing:'.$listing->id)
            ->assertJsonPath('sources.0.title', 'Clarens Mountain B&B')
            ->assertSee('short-term place to stay', false);

        Http::assertNothingSent();
    }

    public function test_ask_life_universally_recommends_matching_business_services(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        $dev = $this->devOwner();
        $listing = Listing::factory()->create([
            'title' => 'Bethlehem Plumbing Pros',
            'excerpt' => 'Local plumbers for leaks, burst pipes, geysers, and emergency pipe repairs.',
            'description' => 'Plumbing services in Bethlehem for household and business water problems.',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->activateListing($listing);

        $this->actingAs($dev)
            ->postJson(route('ask-life.store'), [
                'question' => 'I need a plumber to fix a leak in Bethlehem',
            ])
            ->assertOk()
            ->assertJsonPath('source', 'guided')
            ->assertJsonPath('intent.key', 'business_search')
            ->assertJsonPath('sources.0.id', 'listing:'.$listing->id)
            ->assertJsonPath('sources.0.title', 'Bethlehem Plumbing Pros')
            ->assertSee('Life@ has', false)
            ->assertSee('plumber', false);

        Http::assertNothingSent();
    }

    public function test_ask_life_universally_recommends_matching_vouchers_and_businesses(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        $dev = $this->devOwner();
        $listing = Listing::factory()->create([
            'title' => 'Bethlehem Coffee Bar',
            'excerpt' => 'Breakfast, coffee, light meals, and weekend specials.',
            'description' => 'Local coffee shop with breakfast deals in Bethlehem.',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->activateListing($listing);

        $voucher = Voucher::factory()->create([
            'listing_id' => $listing->id,
            'title' => 'Breakfast Coffee Special',
            'description' => 'Discounted breakfast and coffee special for local customers.',
            'terms' => 'Valid for breakfast orders while the voucher is active.',
            'status' => 'published',
            'published_at' => now(),
            'start_at' => now()->subDay(),
            'end_at' => now()->addWeek(),
        ]);

        $this->actingAs($dev)
            ->postJson(route('ask-life.store'), [
                'question' => 'Show me coffee specials in Bethlehem',
            ])
            ->assertOk()
            ->assertJsonPath('source', 'guided')
            ->assertJsonPath('intent.key', 'voucher_discovery')
            ->assertJsonPath('sources.0.id', 'voucher:'.$voucher->id)
            ->assertJsonPath('sources.0.title', 'Breakfast Coffee Special')
            ->assertJsonFragment(['label' => 'Vouchers'])
            ->assertSee('Life@ has', false);

        Http::assertNothingSent();
    }

    public function test_jimmy_can_answer_conversationally_from_platform_guides(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => 'sk-or-test',
            'services.ai.providers.openrouter.model' => 'openai/gpt-oss-120b',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'answer' => 'I am Ask Life. I can help you find your way around Life@, but I will not pretend we have a verified listing or event when we do not. Tell me the town and what you need, and I will point you to the best next step.',
                                'confidence' => 0.72,
                                'source_ids' => ['guide:search', 'guide:directory'],
                                'follow_up_questions' => ['Which town should I focus on?'],
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($this->devOwner())->postJson(route('ask-life.store'), [
            'question' => 'Jimmy, what can you help me with?',
        ])
            ->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('sources.0.id', 'guide:search')
            ->assertJsonPath('sources.1.id', 'guide:directory')
            ->assertSee('I am Ask Life', false);

        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return str_contains($body, 'strong sense of honour, integrity, and truth')
                && str_contains($body, 'guide:search')
                && str_contains($body, 'Business directory')
                && str_contains($body, 'detected_intent')
                && str_contains($body, 'search_context')
                && str_contains($body, 'platform guide sources');
        });
    }

    public function test_ask_life_feedback_is_persisted(): void
    {
        $this->actingAs($this->devOwner())->postJson(route('ask-life.feedback'), [
            'rating' => 'not_helpful',
            'question' => 'Where can I fix a tyre?',
            'answer' => 'I could not find a direct match yet.',
            'intent' => 'business_search',
            'source' => 'fallback',
            'source_ids' => ['guide:directory'],
            'sources' => [
                ['id' => 'guide:directory', 'type' => 'guide', 'title' => 'Business directory'],
            ],
            'page_context' => [
                'page_type' => 'directory',
                'page_title' => 'Directory',
                'path' => '/directory',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('ask_life_feedback', [
            'rating' => 'not_helpful',
            'intent' => 'business_search',
            'source' => 'fallback',
            'question' => 'Where can I fix a tyre?',
        ]);
    }

    private function devOwner(): User
    {
        return User::query()->where('email', 'jameskoen78@gmail.com')->first()
            ?? User::factory()->create([
                'role' => 'dev',
                'email' => 'jameskoen78@gmail.com',
            ]);
    }

    private function activateListing(Listing $listing): Listing
    {
        $subscription = Subscription::create([
            'user_id' => $listing->user_id,
            'package_id' => Package::query()->firstOrFail()->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonths(6),
            'renewal_mode' => 'manual',
        ]);

        $listing->forceFill([
            'active_subscription_id' => $subscription->id,
        ])->save();

        return $listing->refresh();
    }
}
