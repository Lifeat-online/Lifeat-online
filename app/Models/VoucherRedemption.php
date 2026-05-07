<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VoucherRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'user_id',
        'code',
        'status',
        'claimed_at',
        'consumed_at',
        'consumed_by_user_id',
        'consumed_ip',
        'consumed_user_agent',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by_user_id');
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}

