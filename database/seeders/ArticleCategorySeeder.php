<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class ArticleCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'type' => 'article',
                'name' => 'Local News',
                'slug' => 'local-news',
                'description' => 'Breaking updates, council decisions, and community developments.',
            ],
            [
                'type' => 'article',
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Coverage of local enterprises, markets, and growth opportunities.',
            ],
            [
                'type' => 'article',
                'name' => 'Events',
                'slug' => 'events',
                'description' => 'Upcoming happenings, festivals, and public gatherings.',
            ],
            [
                'type' => 'article',
                'name' => 'Community',
                'slug' => 'community',
                'description' => 'Stories about schools, churches, NGOs, and neighbourhood life.',
            ],
            [
                'type' => 'article',
                'name' => 'Sport',
                'slug' => 'sport',
                'description' => 'Local sports fixtures, results, and athlete highlights.',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['type' => $category['type'], 'slug' => $category['slug']],
                $category
            );
        }
    }
}
