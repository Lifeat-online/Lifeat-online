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
        $this->call([
            ArticleCategorySeeder::class,
            ArticleTagSeeder::class,
            LocationNodeSeeder::class,
            MallStoreCategorySeeder::class,
        ]);

        if ($this->shouldSeedDemoData('mall_demo')) {
            $this->call(MallDemoSeeder::class);
        }

        if ($this->shouldSeedDemoData('demo_users') && ! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        User::updateOrCreate(
            ['email' => 'jameskoen78@gmail.com'],
            [
                'name' => 'James Koen',
                'password' => bcrypt('temp_dev_2026!'),
                'role' => 'dev',
            ]
        );
    }

    private function shouldSeedDemoData(string $key): bool
    {
        if (! (bool) config('seeders.'.$key, false)) {
            return false;
        }

        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn("Skipping {$key} seed data outside local/testing.");

            return false;
        }

        return true;
    }
}
