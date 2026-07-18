<?php

namespace Tests\Feature;

use App\Ai\Contracts\EmbeddingProvider;
use App\Ai\Knowledge\KnowledgeIndexer;
use App\Models\Article;
use App\Models\KnowledgeDocument;
use App\Jobs\SyncArticleKnowledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class KnowledgeIndexingTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_articles_are_indexed_and_drafts_are_removed(): void
    {
        $provider = new class implements EmbeddingProvider
        {
            public int $calls = 0;

            public function embed(array $texts): array
            {
                $this->calls++;

                return array_map(fn () => array_fill(0, 1536, 0.1), $texts);
            }

            public function dimensions(): int
            {
                return 1536;
            }

            public function model(): string
            {
                return 'test-embedding';
            }
        };
        $this->app->instance(EmbeddingProvider::class, $provider);

        $article = Article::create([
            'title' => 'Bethlehem road closure',
            'slug' => 'bethlehem-road-closure',
            'excerpt' => 'Drivers should use an alternative route.',
            'body' => '<p>The N5 access road closes on Saturday from 08:00 to 14:00.</p>',
            'source_locale' => 'en',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $document = app(KnowledgeIndexer::class)->indexArticle($article);

        $this->assertNotNull($document);
        $this->assertSame('article', $document->source_type);
        $this->assertSame((string) $article->id, $document->source_id);
        $this->assertSame(route('articles.show', $article), $document->canonical_url);
        $this->assertStringNotContainsString('<p>', $document->content);
        $this->assertGreaterThan(0, $document->chunks()->count());
        $this->assertSame(1, $provider->calls);

        app(KnowledgeIndexer::class)->indexArticle($article->fresh());
        $this->assertSame(1, $provider->calls, 'Unchanged content must not be embedded twice.');

        $article->update(['slug' => 'bethlehem-road-closure-updated']);
        $document = app(KnowledgeIndexer::class)->indexArticle($article->fresh());
        $this->assertSame(1, $provider->calls, 'Metadata-only changes must not be embedded twice.');
        $this->assertSame(route('articles.show', $article->fresh()), $document->canonical_url);

        $article->update(['status' => 'draft']);

        $this->assertNull(app(KnowledgeIndexer::class)->indexArticle($article->fresh()));
        $this->assertDatabaseMissing('knowledge_documents', [
            'source_type' => 'article',
            'source_id' => (string) $article->id,
        ]);
    }

    public function test_expired_knowledge_documents_are_pruned(): void
    {
        KnowledgeDocument::create([
            'source_type' => 'event', 'source_id' => 'expired', 'locale' => 'en',
            'title' => 'Expired', 'content' => 'Expired event', 'content_hash' => hash('sha256', 'Expired event'),
            'visibility' => 'public', 'published_at' => now()->subDay(), 'expires_at' => now()->subMinute(), 'indexed_at' => now(),
        ]);
        KnowledgeDocument::create([
            'source_type' => 'event', 'source_id' => 'future', 'locale' => 'en',
            'title' => 'Future', 'content' => 'Future event', 'content_hash' => hash('sha256', 'Future event'),
            'visibility' => 'public', 'published_at' => now(), 'expires_at' => now()->addDay(), 'indexed_at' => now(),
        ]);

        $this->artisan('life:knowledge:prune')->expectsOutput('Pruned 1 expired knowledge document(s).')->assertSuccessful();

        $this->assertDatabaseMissing('knowledge_documents', ['source_id' => 'expired']);
        $this->assertDatabaseHas('knowledge_documents', ['source_id' => 'future']);
    }

    public function test_reindex_command_supports_article_filters_and_dry_run(): void
    {
        Article::create([
            'title' => 'Clarens market',
            'slug' => 'clarens-market',
            'body' => 'The community market opens every Saturday.',
            'source_locale' => 'en',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->artisan('life:knowledge:reindex', ['--type' => 'article', '--dry-run' => true])
            ->expectsOutputToContain('1 eligible article')
            ->assertSuccessful();

        $this->assertSame(0, KnowledgeDocument::count());

        $this->artisan('life:knowledge:reindex', ['--type' => 'article'])
            ->assertSuccessful();

        $this->assertSame(1, KnowledgeDocument::count());
    }

    public function test_article_changes_queue_knowledge_sync_after_commit(): void
    {
        Bus::fake();
        config()->set('ai_platform.knowledge.auto_index', true);

        $article = Article::create([
            'title' => 'Queued article',
            'slug' => 'queued-article',
            'body' => 'Public knowledge.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Bus::assertDispatched(SyncArticleKnowledge::class, fn (SyncArticleKnowledge $job): bool => $job->articleId === $article->id);
    }
}
