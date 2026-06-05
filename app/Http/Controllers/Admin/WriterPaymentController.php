<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleWordLedger;
use App\Models\NumberSequence;
use App\Models\WriterPaymentBatch;
use App\Models\WriterPaymentBatchItem;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function storeBatch(Request $request, AuditLogService $audit): RedirectResponse
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

        $batch = DB::transaction(function () use ($ledgers, $request) {
            $batch = WriterPaymentBatch::create([
                'reference' => NumberSequence::next('writer_payment_batch', 'WPB'),
                'created_by_user_id' => $request->user()->id,
                'status' => 'exported',
                'item_count' => $ledgers->count(),
                'gross_amount' => $ledgers->sum(fn (ArticleWordLedger $ledger) => (float) $ledger->gross_amount),
                'exported_at' => now(),
            ]);

            foreach ($ledgers as $ledger) {
                $locked = ArticleWordLedger::whereKey($ledger->id)->lockForUpdate()->first();

                WriterPaymentBatchItem::create([
                    'writer_payment_batch_id' => $batch->id,
                    'article_word_ledger_id' => $locked->id,
                    'gross_amount' => $locked->gross_amount,
                ]);

                $locked->update([
                    'status' => 'batched',
                ]);
            }

            return $batch;
        });

        $audit->log($request, 'writer_payment_batch.created', $batch, [], [
            'status' => $batch->status,
            'item_count' => $batch->item_count,
            'gross_amount' => $batch->gross_amount,
            'ledger_ids' => $ledgers->modelKeys(),
        ]);

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

    public function markPaid(Request $request, WriterPaymentBatch $batch, AuditLogService $audit): RedirectResponse
    {
        if ($batch->status === 'paid') {
            return redirect()->route('admin.writer-payments.index')->with('status', 'Batch already marked paid.');
        }

        $batch->load('items.ledger');
        $before = $batch->only(['status', 'paid_at']);
        $ledgerIds = $batch->items
            ->pluck('ledger')
            ->filter()
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($batch) {
            $lockedBatch = WriterPaymentBatch::whereKey($batch->id)->lockForUpdate()->first();
            $lockedBatch->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            foreach ($lockedBatch->items()->with('ledger')->get() as $item) {
                if (! $item->ledger) {
                    continue;
                }

                $lockedLedger = ArticleWordLedger::whereKey($item->ledger->id)->lockForUpdate()->first();
                $lockedLedger->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }
        });

        $audit->log($request, 'writer_payment_batch.marked_paid', $batch->fresh(), $before, [
            'status' => $batch->fresh()->status,
            'paid_at' => optional($batch->fresh()->paid_at)?->toDateTimeString(),
            'ledger_ids' => $ledgerIds,
        ]);

        return redirect()->route('admin.writer-payments.index')->with('status', 'Batch marked paid.');
    }
}
