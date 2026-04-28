<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriterPaymentBatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'writer_payment_batch_id',
        'article_word_ledger_id',
        'gross_amount',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WriterPaymentBatch::class, 'writer_payment_batch_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(ArticleWordLedger::class, 'article_word_ledger_id');
    }
}
