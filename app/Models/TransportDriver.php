<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TransportDriver extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id',
        'manager_user_id',
        'status',
        'phone',
        'id_number',
        'license_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'can_transport_people',
        'can_transport_parcels',
        'notes',
        'approved_at',
        'approved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'can_transport_people' => 'boolean',
            'can_transport_parcels' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(TransportVehicle::class);
    }

    public function dutySessions(): HasMany
    {
        return $this->hasMany(TransportDutySession::class);
    }

    public function requestOffers(): HasMany
    {
        return $this->hasMany(TransportRequestOffer::class);
    }

    public function activeDutySession(): HasOne
    {
        return $this->hasOne(TransportDutySession::class)->whereNull('ended_at')->latestOfMany();
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
