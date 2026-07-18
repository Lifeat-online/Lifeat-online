<?php

namespace Tests\Feature;

use App\Ai\Contracts\EmbeddingProvider;
use App\Ai\Knowledge\KnowledgeVisibility;
use App\Ai\Providers\FakeEmbeddingProvider;
use App\Ai\Providers\OpenAiEmbeddingProvider;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Services\AiGatewayService;

class AiPlatformFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_platform_defaults_are_safe_and_provider_is_bound(): void
    {
        $this->assertFalse(config('ai_platform.public_chat.enabled'));
        $this->assertFalse(config('ai_platform.public_chat.anonymous_enabled'));
        $this->assertFalse(config('ai_platform.operator.mutations_enabled'));
        $this->assertSame(30, config('ai_platform.public_chat.retention_days'));
        $this->assertSame('text-embedding-3-small', config('ai_platform.embeddings.model'));
        $this->assertInstanceOf(EmbeddingProvider::class, app(EmbeddingProvider::class));
    }

    public function test_fake_embedding_provider_is_deterministic_and_dimensioned(): void
    {
        $provider = new FakeEmbeddingProvider(8);

        $first = $provider->embed(['Bethlehem events', 'Clarens accommodation']);
        $second = $provider->embed(['Bethlehem events', 'Clarens accommodation']);

        $this->assertSame($first, $second);
        $this->assertCount(2, $first);
        $this->assertCount(8, $first[0]);
        $this->assertNotSame($first[0], $first[1]);
    }

    public function test_public_knowledge_schema_and_visibility_are_available(): void
    {
        $this->assertTrue(Schema::hasTable('knowledge_documents'));
        $this->assertTrue(Schema::hasTable('knowledge_chunks'));

        $document = KnowledgeDocument::create([
            'source_type' => 'article',
            'source_id' => '42',
            'locale' => 'en',
            'title' => 'Bethlehem road closure',
            'canonical_url' => '/articles/bethlehem-road-closure',
            'content' => 'The road will be closed on Saturday.',
            'content_hash' => hash('sha256', 'The road will be closed on Saturday.'),
            'visibility' => KnowledgeVisibility::PUBLIC,
            'published_at' => now(),
        ]);

        $chunk = KnowledgeChunk::create([
            'knowledge_document_id' => $document->id,
            'position' => 0,
            'content' => $document->content,
            'content_hash' => $document->content_hash,
            'token_count' => 9,
        ]);

        $this->assertTrue($document->isPublic());
        $this->assertTrue($document->chunks->contains($chunk));
    }

    public function test_openai_embedding_provider_uses_configured_model_and_preserves_order(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['index' => 1, 'embedding' => [0.4, 0.5, 0.6]],
                    ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
                ],
            ]),
        ]);

        $provider = new OpenAiEmbeddingProvider(
            apiKey: 'test-key',
            modelName: 'text-embedding-3-small',
            vectorDimensions: 3,
            baseUrl: 'https://api.openai.com/v1',
        );

        $vectors = $provider->embed(['first', 'second']);

        $this->assertSame([[0.1, 0.2, 0.3], [0.4, 0.5, 0.6]], $vectors);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'text-embedding-3-small'
            && $request['dimensions'] === 3
            && $request['input'] === ['first', 'second']);
    }

    public function test_feature_configuration_rejects_provider_without_required_capability(): void
    {
        config([
            'services.ai.providers.limited' => [
                'label' => 'Limited',
                'type' => 'text_only',
                'key' => 'test',
                'model' => 'limited-model',
                'base_url' => 'https://limited.example/v1',
            ],
            'ai_features.routes.ask_life.provider' => 'limited',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing capabilities: structured_json');
        app(AiGatewayService::class)->assertFeatureCapabilities('ask_life', 'limited');
    }
}
