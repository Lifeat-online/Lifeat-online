<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportVehicle extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'transport_driver_id',
        'manager_user_id',
        'name',
        'vehicle_type',
        'registration_number',
        'status',
        'can_carry_people',
        'can_carry_parcels',
        'max_passengers',
        'max_weight_kg',
        'pricing_mode',
        'base_fee',
        'per_km_fee',
        'per_person_fee',
        'minimum_fee',
        'waiting_fee',
        'cancellation_fee',
        'accepts_cash',
        'has_card_machine',
        'accepts_payfast',
        'approved_at',
        'approved_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'can_carry_people' => 'boolean',
            'can_carry_parcels' => 'boolean',
            'accepts_cash' => 'boolean',
            'has_card_machine' => 'boolean',
            'accepts_payfast' => 'boolean',
            'max_weight_kg' => 'decimal:2',
            'base_fee' => 'decimal:2',
            'per_km_fee' => 'decimal:2',
            'per_person_fee' => 'decimal:2',
            'minimum_fee' => 'decimal:2',
            'waiting_fee' => 'decimal:2',
            'cancellation_fee' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TransportDriver::class, 'transport_driver_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function dutySessions(): HasMany
    {
        return $this->hasMany(TransportDutySession::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
