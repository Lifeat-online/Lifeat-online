<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Entitlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'entitled_type',
        'entitled_id',
        'entitlement_code',
        'active_from',
        'active_until',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'active_from' => 'datetime',
            'active_until' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function entitled(): MorphTo
    {
        return $this->morphTo();
    }
}
