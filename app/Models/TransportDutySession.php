<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportDutySession extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_BUSY = 'busy';
    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'transport_driver_id',
        'transport_vehicle_id',
        'status',
        'started_at',
        'ended_at',
        'last_latitude',
        'last_longitude',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_latitude' => 'decimal:7',
            'last_longitude' => 'decimal:7',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TransportDriver::class, 'transport_driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'transport_vehicle_id');
    }

    public function requestOffers(): HasMany
    {
        return $this->hasMany(TransportRequestOffer::class);
    }

    public function isLive(): bool
    {
        return $this->ended_at === null && in_array($this->status, [self::STATUS_AVAILABLE, self::STATUS_BUSY], true);
    }
}
