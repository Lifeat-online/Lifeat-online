<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CivicFaultReport extends Model
{
    use HasFactory;

    public const STATUS_REPORTED = 'reported';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_URGENT = 'urgent';

    protected $fillable = [
        'client_uuid',
        'reporter_user_id',
        'assigned_councillor_id',
        'category',
        'severity',
        'status',
        'address_label',
        'latitude',
        'longitude',
        'description',
        'consented_at',
        'is_approved',
        'moderated_by_user_id',
        'moderated_at',
        'rejected_at',
        'rejection_reason',
        'acknowledged_at',
        'in_progress_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_approved' => 'boolean',
            'moderated_at' => 'datetime',
            'rejected_at' => 'datetime',
            'consented_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'in_progress_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $report) {
            if (! $report->client_uuid) {
                $report->client_uuid = (string) Str::uuid();
            }
        });
    }

    public static function categories(): array
    {
        return [
            'pothole' => 'Pothole',
            'burst_pipe' => 'Burst Pipe / Water Leak',
            'streetlight' => 'Damaged Streetlight',
            'sidewalk' => 'Broken Sidewalk',
            'sanitation' => 'Sanitation / Illegal Dumping',
            'electricity' => 'Electricity Outage / Hazard',
            'other' => 'Other',
        ];
    }

    public static function severities(): array
    {
        return [
            self::SEVERITY_LOW => 'Low',
            self::SEVERITY_MEDIUM => 'Medium',
            self::SEVERITY_HIGH => 'High',
            self::SEVERITY_URGENT => 'Urgent',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_REPORTED => 'Reported',
            self::STATUS_ACKNOWLEDGED => 'Acknowledged',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RESOLVED => 'Resolved',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function assignedCouncillor(): BelongsTo
    {
        return $this->belongsTo(Councillor::class, 'assigned_councillor_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CivicFaultPhoto::class);
    }
}
