<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportRequestOffer extends Model
{
    use HasFactory;

    public const STATUS_OFFERED = 'offered';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'transport_request_id',
        'transport_driver_id',
        'transport_vehicle_id',
        'transport_duty_session_id',
        'status',
        'quoted_amount',
        'platform_fee',
        'driver_amount',
        'offered_at',
        'accepted_at',
        'declined_at',
    ];

    protected function casts(): array
    {
        return [
            'quoted_amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'driver_amount' => 'decimal:2',
            'offered_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(TransportRequest::class, 'transport_request_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TransportDriver::class, 'transport_driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'transport_vehicle_id');
    }

    public function dutySession(): BelongsTo
    {
        return $this->belongsTo(TransportDutySession::class, 'transport_duty_session_id');
    }
}
