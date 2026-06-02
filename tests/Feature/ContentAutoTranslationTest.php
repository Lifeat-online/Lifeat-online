<?php

namespace Tests\Feature;

use App\Jobs\TranslatePublishedContent;
use App\Models\Article;
use App\Models\User;
use App\Services\OpenRouterTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class ContentAutoTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_translatable_content_queues_translation_job(): void
    {
        config(['localization.auto_translate_on_publish' => true]);
        Queue::fake();

        $this->mock(OpenRouterTranslationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('configured')->once()->andReturn(true);
        });

        $author = User::factory()->create();
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Bethlehem Water Repairs',
            'slug' => 'auto-translation-bethlehem-water-repairs',
            'excerpt' => 'Water repairs are planned this week.',
            'body' => 'Municipal teams will repair water lines in Bethlehem this week.',
            'source_locale' => 'en',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Queue::assertPushed(TranslatePublishedContent::class, function (TranslatePublishedContent $job) use ($article): bool {
            return $job->translatableType === Article::class
                && $job->translatableId === $article->id;
        });
    }

    public function test_draft_translatable_content_does_not_queue_translation_job(): void
    {
        config(['localization.auto_translate_on_publish' => true]);
        Queue::fake();

        $this->mock(OpenRouterTranslationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('configured')->never();
        });

        $author = User::factory()->create();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Draft Water Repairs',
            'slug' => 'draft-water-repairs',
            'excerpt' => 'Draft repairs note.',
            'body' => 'This story is not ready yet.',
            'source_locale' => 'en',
            'status' => 'draft',
            'published_at' => null,
        ]);

        Queue::assertNotPushed(TranslatePublishedContent::class);
    }

    public function test_translation_job_translates_supported_target_locales(): void
    {
        $author = User::factory()->create();
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Clarens Market',
            'slug' => 'clarens-market-auto-job',
            'excerpt' => 'A local market opens this weekend.',
            'body' => 'The market will feature local makers and food stalls.',
            'source_locale' => 'en',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $translator = $this->mock(OpenRouterTranslationService::class, function (MockInterface $mock) use ($article): void {
            $mock->shouldReceive('configured')->once()->andReturn(true);
            $mock->shouldReceive('translateModel')
                ->once()
                ->withArgs(fn (Article $model, string $locale, bool $force): bool => $model->is($article)
                    && $locale === 'af'
                    && $force === false)
                ->andReturn(['ok' => true]);
        });

        (new TranslatePublishedContent(Article::class, $article->id))->handle($translator);
    }
}
