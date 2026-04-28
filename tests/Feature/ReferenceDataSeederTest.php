<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LocationNode;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReferenceDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_adds_article_reference_data(): void
    {
        Artisan::call('db:seed');

        $this->assertDatabaseHas('categories', [
            'type' => 'article',
            'slug' => 'local-news',
        ]);

        $this->assertDatabaseHas('tags', [
            'type' => 'article',
            'slug' => 'breaking',
        ]);

        $this->assertDatabaseHas('location_nodes', [
            'slug' => 'free-state',
            'type' => 'province',
        ]);

        $this->assertDatabaseHas('location_nodes', [
            'slug' => 'bethlehem',
        ]);

        $freeState = LocationNode::where('slug', 'free-state')->firstOrFail();
        $bethlehem = LocationNode::where('slug', 'bethlehem')->firstOrFail();

        $this->assertSame($freeState->id, $bethlehem->parent_id);
        $this->assertGreaterThanOrEqual(5, Category::where('type', 'article')->count());
        $this->assertGreaterThanOrEqual(5, Tag::where('type', 'article')->count());
    }
}
