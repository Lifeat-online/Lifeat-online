<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'provider',
        'status',
        'request_payload_json',
        'response_payload_json',
        'redirect_url',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload_json' => 'array',
            'response_payload_json' => 'array',
            'attempted_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
