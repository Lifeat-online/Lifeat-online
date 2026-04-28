<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class ArticleTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            [
                'type' => 'article',
                'name' => 'Breaking',
                'slug' => 'breaking',
                'description' => 'Fast-moving local updates that matter right now.',
            ],
            [
                'type' => 'article',
                'name' => 'Feature',
                'slug' => 'feature',
                'description' => 'Longer-form reporting and local interest stories.',
            ],
            [
                'type' => 'article',
                'name' => 'Opinion',
                'slug' => 'opinion',
                'description' => 'Commentary, perspectives, and editorial viewpoints.',
            ],
            [
                'type' => 'article',
                'name' => 'Community',
                'slug' => 'community',
                'description' => 'Coverage focused on civic and neighbourhood life.',
            ],
            [
                'type' => 'article',
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Commercial developments, entrepreneurs, and local trade.',
            ],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['type' => $tag['type'], 'slug' => $tag['slug']],
                $tag
            );
        }
    }
}
