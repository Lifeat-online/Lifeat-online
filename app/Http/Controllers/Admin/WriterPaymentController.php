<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleWordLedger;
use App\Models\WriterPaymentBatch;
use App\Models\WriterPaymentBatchItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WriterPaymentController extends Controller
{
    public function index(): View
    {
        return view('admin.writer-payments.index', [
            'pendingLedgers' => ArticleWordLedger::with(['article', 'writer', 'approver'])
                ->where('status', 'pending')
                ->orderByDesc('approved_at')
                ->get(),
            'batches' => WriterPaymentBatch::with(['creator'])
                ->latest()
                ->paginate(12),
        ]);
    }

    public function storeBatch(Request $request): RedirectResponse
    {
        $ids = ArticleWordLedger::query()
            ->where('status', 'pending')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return redirect()->route('admin.writer-payments.index')->with('status', 'No pending ledger entries to batch.');
        }

        $ledgers = ArticleWordLedger::with(['article', 'writer'])
            ->whereIn('id', $ids)
            ->get();

        $batch = WriterPaymentBatch::create([
            'reference' => 'WPB-'.now()->format('YmdHis'),
            'created_by_user_id' => $request->user()->id,
            'status' => 'exported',
            'item_count' => $ledgers->count(),
            'gross_amount' => $ledgers->sum(fn (ArticleWordLedger $ledger) => (float) $ledger->gross_amount),
            'exported_at' => now(),
        ]);

        foreach ($ledgers as $ledger) {
            WriterPaymentBatchItem::create([
                'writer_payment_batch_id' => $batch->id,
                'article_word_ledger_id' => $ledger->id,
                'gross_amount' => $ledger->gross_amount,
            ]);

            $ledger->update([
                'status' => 'batched',
            ]);
        }

        return redirect()->route('admin.writer-payments.index')->with('status', 'Writer payment batch created.');
    }

    public function export(WriterPaymentBatch $batch): StreamedResponse
    {
        $batch->load(['items.ledger.article', 'items.ledger.writer']);

        return response()->streamDownload(function () use ($batch) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Batch Ref', 'Writer', 'Article', 'Word Count', 'Rate', 'Gross Amount', 'Ledger Status']);

            foreach ($batch->items as $item) {
                $ledger = $item->ledger;
                fputcsv($handle, [
                    $batch->reference,
                    $ledger->writer?->name,
                    $ledger->article?->title,
                    $ledger->word_count,
                    $ledger->rate_per_word,
                    $ledger->gross_amount,
                    $ledger->status,
                ]);
            }

            fclose($handle);
        }, $batch->reference.'.csv');
    }

    public function markPaid(Request $request, WriterPaymentBatch $batch): RedirectResponse
    {
        if ($batch->status === 'paid') {
            return redirect()->route('admin.writer-payments.index')->with('status', 'Batch already marked paid.');
        }

        $batch->load('items.ledger');
        $batch->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        foreach ($batch->items as $item) {
            $item->ledger->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        return redirect()->route('admin.writer-payments.index')->with('status', 'Batch marked paid.');
    }
}
