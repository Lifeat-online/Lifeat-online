<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'listing_id' => Listing::factory(),
            'created_by_user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'voucher_type' => Voucher::TYPE_DISCOUNT_PERCENT,
            'discount_percent' => fake()->randomFloat(2, 5, 50),
            'currency' => 'ZAR',
            'usage_limit' => 10,
            'redemptions_count' => 0,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(14),
            'terms' => fake()->paragraph(),
            'status' => 'published',
            'published_at' => now(),
        ];
    }
}

