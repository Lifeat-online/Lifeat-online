<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Councillor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'email',
        'office_address',
        'portfolios',
        'category_responsibilities',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'portfolios' => 'array',
            'category_responsibilities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(CouncillorArea::class);
    }

    public function assignedFaultReports(): HasMany
    {
        return $this->hasMany(CivicFaultReport::class, 'assigned_councillor_id');
    }
}

