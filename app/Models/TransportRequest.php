<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportRequest extends Model
{
    use HasFactory;

    public const STATUS_DISPATCHING = 'dispatching';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DRIVER_ARRIVING = 'driver_arriving';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'accepted_transport_driver_id',
        'accepted_transport_vehicle_id',
        'request_number',
        'service_type',
        'status',
        'payment_method',
        'request_timing',
        'scheduled_pickup_at',
        'dispatch_started_at',
        'pickup_address',
        'dropoff_address',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_latitude',
        'dropoff_longitude',
        'distance_km',
        'passenger_count',
        'parcel_weight_kg',
        'required_vehicle_type',
        'client_notes',
        'quoted_amount',
        'platform_fee',
        'driver_amount',
        'currency',
        'accepted_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'dropoff_latitude' => 'decimal:7',
            'dropoff_longitude' => 'decimal:7',
            'distance_km' => 'decimal:2',
            'parcel_weight_kg' => 'decimal:2',
            'quoted_amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'driver_amount' => 'decimal:2',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'scheduled_pickup_at' => 'datetime',
            'dispatch_started_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acceptedDriver(): BelongsTo
    {
        return $this->belongsTo(TransportDriver::class, 'accepted_transport_driver_id');
    }

    public function acceptedVehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'accepted_transport_vehicle_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(TransportRequestOffer::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(TransportRequestStatusEvent::class);
    }
}
