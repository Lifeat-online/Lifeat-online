<?php

namespace Tests\Feature;

use App\Ai\Contracts\EmbeddingProvider;
use App\Ai\Knowledge\KnowledgeRetriever;
use App\Ai\Knowledge\KnowledgeVisibility;
use App\Models\KnowledgeDocument;
use App\Services\AskLifeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeRetrievalTest extends TestCase
{
    use RefreshDatabase;

    public function test_hybrid_retrieval_ranks_public_evidence_and_never_leaks_private_content(): void
    {
        $this->app->instance(EmbeddingProvider::class, new class implements EmbeddingProvider
        {
            public function embed(array $texts): array
            {
                return array_map(fn (string $text) => str_contains(strtolower($text), 'market')
                    ? [1.0, 0.0, 0.0]
                    : [0.0, 1.0, 0.0], $texts);
            }

            public function dimensions(): int { return 3; }

            public function model(): string { return 'retrieval-test'; }
        });

        $public = $this->document('Clarens community market', KnowledgeVisibility::PUBLIC, now());
        $public->chunks()->create([
            'position' => 0,
            'content' => 'The Clarens community market opens every Saturday morning.',
            'content_hash' => hash('sha256', 'market'),
            'token_count' => 9,
            'embedding' => [1.0, 0.0, 0.0],
            'embedding_model' => 'retrieval-test',
            'embedding_dimensions' => 3,
        ]);

        $private = $this->document('Secret market draft', KnowledgeVisibility::PRIVATE, null);
        $private->chunks()->create([
            'position' => 0,
            'content' => 'Secret market plans must never be returned.',
            'content_hash' => hash('sha256', 'secret'),
            'token_count' => 7,
            'embedding' => [1.0, 0.0, 0.0],
            'embedding_model' => 'retrieval-test',
            'embedding_dimensions' => 3,
        ]);

        $results = app(KnowledgeRetriever::class)->search('When is the Clarens market?', 'en', 5);

        $this->assertCount(1, $results);
        $this->assertSame($public->id, $results[0]['document_id']);
        $this->assertSame('Clarens community market', $results[0]['title']);
        $this->assertGreaterThan(0, $results[0]['score']);
        $this->assertStringNotContainsString('Secret', $results[0]['content']);
    }

    public function test_ask_life_merges_hybrid_knowledge_sources_behind_feature_flag(): void
    {
        config()->set('ai_platform.public_chat.hybrid_retrieval_enabled', true);
        $this->app->instance(EmbeddingProvider::class, new class implements EmbeddingProvider
        {
            public function embed(array $texts): array { return array_map(fn () => [1.0, 0.0], $texts); }
            public function dimensions(): int { return 2; }
            public function model(): string { return 'hybrid-test'; }
        });

        $document = $this->document('Fouriesburg farmers market', KnowledgeVisibility::PUBLIC, now());
        $document->chunks()->create([
            'position' => 0,
            'content' => 'The Fouriesburg farmers market trades on Friday mornings.',
            'content_hash' => hash('sha256', 'fouriesburg-market'),
            'token_count' => 9,
            'embedding' => [1.0, 0.0],
            'embedding_model' => 'hybrid-test',
            'embedding_dimensions' => 2,
        ]);

        $sources = app(AskLifeService::class)->sourcesForQuestion('Fouriesburg farmers market');

        $this->assertTrue($sources->contains('id', 'article:'.$document->source_id));
        $this->assertSame('hybrid', $sources->firstWhere('id', 'article:'.$document->source_id)['meta']['retrieval']);
    }

    private function document(string $title, string $visibility, $publishedAt): KnowledgeDocument
    {
        return KnowledgeDocument::create([
            'source_type' => 'article',
            'source_id' => (string) fake()->unique()->numberBetween(1, 100000),
            'locale' => 'en',
            'title' => $title,
            'canonical_url' => '/articles/'.str($title)->slug(),
            'content' => $title,
            'content_hash' => hash('sha256', $title),
            'visibility' => $visibility,
            'published_at' => $publishedAt,
        ]);
    }
}
