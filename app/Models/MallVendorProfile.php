<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MallVendorProfile extends Model
{
    protected $fillable = [
        'mall_store_id',
        'user_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'business_reg',
        'bank_name',
        'bank_account',
        'bank_branch_code',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MallStore::class, 'mall_store_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
