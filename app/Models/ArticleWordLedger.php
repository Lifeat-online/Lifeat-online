<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ArticleWordLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'writer_user_id',
        'approved_by_user_id',
        'word_count',
        'rate_per_word',
        'gross_amount',
        'status',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'rate_per_word' => 'decimal:2',
            'gross_amount' => 'decimal:2',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'writer_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function batchItem(): HasOne
    {
        return $this->hasOne(WriterPaymentBatchItem::class, 'article_word_ledger_id');
    }
}
