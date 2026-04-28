<?php

namespace Database\Seeders;

use App\Models\LocationNode;
use Illuminate\Database\Seeder;

class LocationNodeSeeder extends Seeder
{
    public function run(): void
    {
        $freeState = LocationNode::updateOrCreate(
            ['slug' => 'free-state'],
            [
                'name' => 'Free State',
                'type' => 'province',
                'parent_id' => null,
            ]
        );

        $locations = [
            ['name' => 'Bethlehem', 'slug' => 'bethlehem', 'type' => 'town'],
            ['name' => 'Harrismith', 'slug' => 'harrismith', 'type' => 'town'],
            ['name' => 'Clarens', 'slug' => 'clarens', 'type' => 'town'],
            ['name' => 'Phuthaditjhaba', 'slug' => 'phuthaditjhaba', 'type' => 'town'],
            ['name' => 'Kestell', 'slug' => 'kestell', 'type' => 'town'],
        ];

        foreach ($locations as $location) {
            LocationNode::updateOrCreate(
                ['slug' => $location['slug']],
                $location + ['parent_id' => $freeState->id]
            );
        }
    }
}
