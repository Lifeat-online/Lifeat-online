<?php

namespace Database\Seeders;

use App\Models\MallStoreCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MallStoreCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Fashion', 'icon' => 'shirt'],
            ['name' => 'Electronics', 'icon' => 'device-phone-mobile'],
            ['name' => 'Food', 'icon' => 'utensils'],
            ['name' => 'Home', 'icon' => 'home'],
            ['name' => 'Health', 'icon' => 'heart'],
            ['name' => 'Services', 'icon' => 'sparkles'],
        ] as $index => $category) {
            MallStoreCategory::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
