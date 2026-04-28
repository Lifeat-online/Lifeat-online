<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleWordLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WriterPaymentBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_pay_writer_batch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $writer = User::factory()->create(['role' => 'writer']);

        $article = Article::create([
            'user_id' => $writer->id,
            'title' => 'Ledger Story',
            'slug' => 'ledger-story',
            'excerpt' => 'Excerpt',
            'body' => 'Body words for the ledger.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $ledger = ArticleWordLedger::create([
            'article_id' => $article->id,
            'writer_user_id' => $writer->id,
            'approved_by_user_id' => $admin->id,
            'word_count' => 10,
            'rate_per_word' => 1.50,
            'gross_amount' => 15.00,
            'status' => 'pending',
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.writer-payments.batches.store'));
        $response->assertRedirect(route('admin.writer-payments.index'));

        $this->assertDatabaseHas('writer_payment_batches', [
            'item_count' => 1,
            'gross_amount' => 15.00,
        ]);

        $ledger->refresh();
        $this->assertSame('batched', $ledger->status);

        $batch = \App\Models\WriterPaymentBatch::firstOrFail();
        $this->actingAs($admin)->post(route('admin.writer-payments.batches.mark-paid', $batch))
            ->assertRedirect(route('admin.writer-payments.index'));

        $ledger->refresh();
        $this->assertSame('paid', $ledger->status);
        $this->assertNotNull($ledger->paid_at);
    }

    public function test_admin_can_export_writer_batch_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $writer = User::factory()->create(['role' => 'writer']);

        $article = Article::create([
            'user_id' => $writer->id,
            'title' => 'Batch Export Story',
            'slug' => 'batch-export-story',
            'excerpt' => 'Excerpt',
            'body' => 'Body words for export.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $ledger = ArticleWordLedger::create([
            'article_id' => $article->id,
            'writer_user_id' => $writer->id,
            'approved_by_user_id' => $admin->id,
            'word_count' => 8,
            'rate_per_word' => 2.00,
            'gross_amount' => 16.00,
            'status' => 'pending',
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.writer-payments.batches.store'));
        $batch = \App\Models\WriterPaymentBatch::firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.writer-payments.batches.export', $batch));

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }
}
