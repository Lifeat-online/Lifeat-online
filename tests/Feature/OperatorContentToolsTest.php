<?php

namespace Tests\Feature;

use App\Ai\Editorial\Contracts\HostResolver;
use App\Ai\Operator\Contracts\OperatorTaskPlanner;
use App\Ai\Operator\Contracts\WebSearchProvider;
use App\Ai\Operator\OperatorTaskOrchestrator;
use App\Ai\Operator\OperatorToolRegistry;
use App\Models\Article;
use App\Models\Category;
use App\Models\Listing;
use App\Models\OperatorConversation;
use App\Models\OperatorTask;
use App\Models\ResearchItem;
use App\Models\ResearchSource;
use App\Models\SourceSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperatorContentToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai_platform.operator.enabled' => true,
            'ai_platform.operator.agent_enabled' => true,
            'ai_platform.operator.r1_auto_enabled' => true,
        ]);
    }

    public function test_developer_can_search_the_public_web_through_a_registered_tool(): void
    {
        $this->app->instance(WebSearchProvider::class, new FakeWebSearchProvider([
            ['title' => 'Acme Bakery', 'url' => 'https://acme.example/about', 'snippet' => 'A family bakery in Bethlehem.', 'published_at' => null, 'source' => 'Acme'],
        ]));
        $dev = User::factory()->create(['role' => 'dev']);

        $response = $this->actingAs($dev)->postJson(route('admin.ai-operator.tools.execute', 'research.web_search'), [
            'arguments' => ['query' => 'Acme Bakery Bethlehem', 'limit' => 5],
            'idempotency_key' => 'web-search-acme',
        ])->assertOk();

        $response->assertJsonPath('risk', 'R0')
            ->assertJsonPath('result.results.0.url', 'https://acme.example/about');
    }

    public function test_developer_can_capture_a_task_scoped_public_source_snapshot(): void
    {
        $this->app->instance(HostResolver::class, new PublicHostResolver);
        Http::fake([
            'https://acme.example/about' => Http::response('<html><body><h1>Acme Bakery</h1><p>12 Main Street, Bethlehem.</p></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $dev = User::factory()->create(['role' => 'dev']);

        $response = $this->actingAs($dev)->postJson(route('admin.ai-operator.tools.execute', 'research.snapshot_source'), [
            'arguments' => [
                'url' => 'https://acme.example/about',
                'title' => 'Acme Bakery official website',
                'snippet' => 'A family bakery in Bethlehem.',
                'source_name' => 'Acme Bakery',
            ],
            'idempotency_key' => 'snapshot-acme',
        ])->assertOk();

        $snapshotId = $response->json('result.snapshot_id');
        $this->assertNotNull($snapshotId);
        $this->assertDatabaseHas('source_snapshots', ['id' => $snapshotId, 'url' => 'https://acme.example/about']);
        $this->assertStringContainsString('12 Main Street', SourceSnapshot::findOrFail($snapshotId)->content);
    }

    public function test_directory_tool_creates_an_unclaimed_published_listing_with_provenance_and_prevents_duplicates(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $snapshot = $this->snapshot('https://acme.example/about', 'Acme Bakery official website');

        $arguments = [
            'title' => 'Acme Bakery',
            'description' => 'A family bakery serving Bethlehem.',
            'website_url' => 'https://acme.example',
            'email' => 'hello@acme.example',
            'phone' => '+27 51 555 0101',
            'address_line' => '12 Main Street',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'country' => 'South Africa',
            'category_names' => ['Bakery'],
            'source_snapshot_ids' => [$snapshot->id],
            'publish' => true,
        ];

        $first = $this->actingAs($dev)->postJson(route('admin.ai-operator.tools.execute', 'directory.create_listing'), [
            'arguments' => $arguments,
            'idempotency_key' => 'create-acme-1',
        ])->assertOk();
        $listing = Listing::findOrFail($first->json('result.listing_id'));

        $this->assertNull($listing->user_id);
        $this->assertSame($dev->id, $listing->registered_by_user_id);
        $this->assertSame('ai_operator', $listing->source_channel);
        $this->assertSame('published', $listing->status);
        $this->assertTrue($listing->categories()->where('slug', 'bakery')->exists());
        $this->assertDatabaseHas('content_source_links', [
            'source_snapshot_id' => $snapshot->id,
            'sourceable_type' => Listing::class,
            'sourceable_id' => $listing->id,
        ]);

        $duplicate = $this->postJson(route('admin.ai-operator.tools.execute', 'directory.create_listing'), [
            'arguments' => $arguments,
            'idempotency_key' => 'create-acme-2',
        ])->assertOk();
        $duplicate->assertJsonPath('result.duplicate', true);
        $this->assertDatabaseCount('listings', 1);
    }

    public function test_event_article_tool_builds_evidence_and_publishes_when_two_sources_support_it(): void
    {
        $this->configureOpenRouter();
        $this->fakeJimmyResponse();
        $dev = User::factory()->create(['role' => 'dev']);
        $category = Category::create(['type' => 'article', 'name' => 'Local News', 'slug' => 'local-news']);
        $primary = $this->snapshot('https://municipality.example/events/water-repairs', 'Municipal water repair notice');
        $secondary = $this->snapshot('https://localnews.example/water-repairs', 'Local reporting on water repairs');

        $response = $this->actingAs($dev)->postJson(route('admin.ai-operator.tools.execute', 'editorial.create_event_article'), [
            'arguments' => [
                'title' => 'Bethlehem water repairs scheduled',
                'angle' => 'Explain the repair schedule and what residents should expect.',
                'primary_snapshot_id' => $primary->id,
                'source_snapshot_ids' => [$primary->id, $secondary->id],
                'category_id' => $category->id,
                'publish' => true,
            ],
            'idempotency_key' => 'event-article-water-repairs',
        ])->assertOk();

        $article = Article::findOrFail($response->json('result.article_id'));
        $this->assertSame('published', $article->status);
        $this->assertNotNull($article->article_brief_id);
        $this->assertDatabaseHas('editorial_dossiers', ['id' => $article->brief->editorial_dossier_id, 'status' => 'approved']);
        $this->assertDatabaseCount('content_source_links', 2);
        $this->assertDatabaseHas('article_word_ledgers', ['article_id' => $article->id, 'approved_by_user_id' => $dev->id]);
    }

    public function test_orchestrated_content_task_records_the_source_snapshots_it_used(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $snapshot = $this->snapshot('https://acme.example/about', 'Acme Bakery official website');
        $conversation = OperatorConversation::create(['user_id' => $dev->id, 'title' => 'Add Acme', 'last_activity_at' => now()]);
        $task = $conversation->tasks()->create([
            'user_id' => $dev->id,
            'goal' => 'Add Acme Bakery to the directory.',
            'status' => OperatorTask::STATUS_PLANNED,
            'step_limit' => 4,
            'usage' => ['steps' => 0, 'cost' => 0],
        ]);
        $this->app->instance(OperatorTaskPlanner::class, new ContentSequencePlanner([
            [
                'action' => 'tool',
                'tool' => 'directory.create_listing',
                'arguments' => [
                    'title' => 'Acme Bakery',
                    'description' => 'A family bakery.',
                    'website_url' => 'https://acme.example',
                    'city' => 'Bethlehem',
                    'country' => 'South Africa',
                    'category_names' => ['Bakery'],
                    'source_snapshot_ids' => [$snapshot->id],
                    'publish' => true,
                ],
                'summary' => 'Create the sourced listing.',
            ],
            ['action' => 'complete', 'summary' => 'Acme Bakery was added.'],
        ]));

        app(OperatorTaskOrchestrator::class)->run($task->id);

        $this->assertSame([$snapshot->id], $task->fresh()->sources);
        $this->actingAs($dev)->getJson(route('admin.ai-operator.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('sources.0.id', $snapshot->id)
            ->assertJsonPath('sources.0.url', 'https://acme.example/about')
            ->assertJsonPath('sources.0.host', 'acme.example');
    }

    public function test_developer_editor_catalog_exposes_research_and_safe_content_management_tools(): void
    {
        $names = collect(app(OperatorToolRegistry::class)->all())->map->name();

        foreach ([
            'content.search',
            'directory.find_duplicates',
            'directory.create_listing',
            'directory.update_listing',
            'research.web_search',
            'research.snapshot_source',
            'research.compare_sources',
            'research.build_dossier',
            'editorial.create_event_article',
            'editorial.update_article',
        ] as $name) {
            $this->assertTrue($names->contains($name), $name.' is not registered.');
        }
    }

    public function test_weak_event_evidence_keeps_the_article_draft_and_pauses_for_developer_input(): void
    {
        $this->configureOpenRouter();
        $this->fakeJimmyResponse();
        $dev = User::factory()->create(['role' => 'dev']);
        $primary = $this->snapshot('https://single-source.example/event', 'Single event announcement');
        $conversation = OperatorConversation::create(['user_id' => $dev->id, 'title' => 'Write event article', 'last_activity_at' => now()]);
        $task = $conversation->tasks()->create([
            'user_id' => $dev->id,
            'goal' => 'Write an article about the event.',
            'status' => OperatorTask::STATUS_PLANNED,
            'step_limit' => 4,
            'usage' => ['steps' => 0, 'cost' => 0],
        ]);
        $this->app->instance(OperatorTaskPlanner::class, new ContentSequencePlanner([[
            'action' => 'tool',
            'tool' => 'editorial.create_event_article',
            'arguments' => [
                'title' => 'Community event announced',
                'angle' => 'Explain what residents need to know.',
                'primary_snapshot_id' => $primary->id,
                'source_snapshot_ids' => [$primary->id],
                'publish' => true,
            ],
            'summary' => 'Draft the sourced event article.',
        ]]));

        app(OperatorTaskOrchestrator::class)->run($task->id);

        $this->assertSame(OperatorTask::STATUS_WAITING_FOR_INPUT, $task->fresh()->status);
        $this->assertSame('draft', Article::query()->latest('id')->firstOrFail()->status);
        $this->assertDatabaseHas('operator_task_steps', ['operator_task_id' => $task->id, 'action' => 'ask_user', 'status' => OperatorTask::STATUS_WAITING_FOR_INPUT]);
    }

    private function snapshot(string $url, string $title): SourceSnapshot
    {
        $host = parse_url($url, PHP_URL_HOST);
        $source = ResearchSource::create([
            'name' => $host,
            'slug' => 'test-'.str()->uuid(),
            'type' => 'web_search',
            'url' => 'https://'.$host,
            'is_active' => false,
            'metadata' => ['allowed_hosts' => [$host], 'trust_score' => 80],
        ]);
        $item = ResearchItem::create([
            'research_source_id' => $source->id,
            'source_name' => $host,
            'source_type' => 'web_search',
            'source_url' => $url,
            'title' => $title,
            'summary' => $title,
            'fetched_at' => now(),
            'published_at' => now(),
            'fingerprint' => hash('sha256', $url),
            'status' => ResearchItem::STATUS_NEW,
        ]);

        return SourceSnapshot::create([
            'research_item_id' => $item->id,
            'url' => $url,
            'http_status' => 200,
            'content_type' => 'text/html',
            'content' => $title.' Evidence and confirmed details.',
            'content_hash' => hash('sha256', $url.' content'),
            'response_headers' => [],
            'fetched_at' => now(),
        ]);
    }

    private function configureOpenRouter(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => 'sk-or-test',
            'services.ai.providers.openrouter.model' => 'openai/gpt-oss-120b',
            'localization.supported' => ['en' => 'English', 'af' => 'Afrikaans'],
        ]);
    }

    private function fakeJimmyResponse(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'title' => 'Bethlehem water repair timeline confirmed',
                    'slug' => 'bethlehem-water-repair-timeline',
                    'excerpt' => 'Repair teams are scheduled to work on Bethlehem water lines.',
                    'body' => 'Bethlehem residents should prepare for scheduled water repair work. Municipal information and local reporting confirm the planned work.',
                    'seo_title' => 'Bethlehem Water Repair Timeline',
                    'seo_description' => 'What residents should know about scheduled Bethlehem water repairs.',
                    'afrikaans_translation' => ['title' => 'Bethlehem-waterherstel bevestig', 'excerpt' => 'Herstelspanne sal aan waterlyne werk.', 'body' => 'Bethlehem-inwoners moet vir waterherstelwerk voorberei.'],
                    'suggested_tags' => ['Bethlehem', 'Water'],
                    'source_notes' => 'Used the official notice and corroborating local report.',
                    'editorial_flags' => [],
                    'image_prompt' => '',
                ])]]],
            ]),
        ]);
    }
}

class FakeWebSearchProvider implements WebSearchProvider
{
    public function __construct(private readonly array $results) {}

    public function search(string $query, string $locale = 'en-ZA', int $limit = 8): array
    {
        return array_slice($this->results, 0, $limit);
    }
}

class PublicHostResolver implements HostResolver
{
    public function addresses(string $host): array
    {
        return ['93.184.216.34'];
    }
}

class ContentSequencePlanner implements OperatorTaskPlanner
{
    public function __construct(private array $actions) {}

    public function nextAction(OperatorTask $task, User $user, array $tools): array
    {
        return array_shift($this->actions) ?? ['action' => 'complete', 'summary' => 'Done.'];
    }
}
