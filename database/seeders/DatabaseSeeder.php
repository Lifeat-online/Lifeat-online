<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            ArticleCategorySeeder::class,
            ArticleTagSeeder::class,
            LocationNodeSeeder::class,
            MallStoreCategorySeeder::class,
        ]);

        if ((bool) env('MALL_SEED_DEMO', false)) {
            $this->call(MallDemoSeeder::class);
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
