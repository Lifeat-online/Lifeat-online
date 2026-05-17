<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportRequestStatusEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'transport_request_id',
        'actor_user_id',
        'status',
        'notes',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(TransportRequest::class, 'transport_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
