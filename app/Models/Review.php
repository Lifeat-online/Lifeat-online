<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'user_id',
        'responded_by_user_id',
        'author_name',
        'author_email',
        'rating',
        'title',
        'body',
        'owner_response',
        'owner_responded_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'owner_responded_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }
}
