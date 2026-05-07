<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CivicFaultPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'civic_fault_report_id',
        'path',
        'original_name',
        'sort_order',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(CivicFaultReport::class, 'civic_fault_report_id');
    }
}

