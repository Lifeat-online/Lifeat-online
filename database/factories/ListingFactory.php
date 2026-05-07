<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    protected $model = Listing::class;

    public function definition(): array
    {
        $title = fake()->company();

        return [
            'user_id' => User::factory(),
            'source_channel' => 'self_service',
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'excerpt' => fake()->sentence(),
            'description' => fake()->paragraphs(3, true),
            'website_url' => fake()->url(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'region' => fake()->state(),
            'country' => fake()->country(),
            'postal_code' => fake()->postcode(),
            'status' => 'published',
            'published_at' => now(),
        ];
    }
}

