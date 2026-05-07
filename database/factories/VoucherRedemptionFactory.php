<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VoucherRedemption>
 */
class VoucherRedemptionFactory extends Factory
{
    protected $model = VoucherRedemption::class;

    public function definition(): array
    {
        return [
            'voucher_id' => Voucher::factory(),
            'user_id' => User::factory(),
            'code' => Str::upper(Str::random(10)),
            'status' => 'claimed',
            'claimed_at' => now(),
            'consumed_at' => null,
            'consumed_by_user_id' => null,
        ];
    }
}

