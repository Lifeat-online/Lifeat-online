<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WriterPaymentBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'created_by_user_id',
        'status',
        'item_count',
        'gross_amount',
        'exported_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'exported_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WriterPaymentBatchItem::class);
    }
}
