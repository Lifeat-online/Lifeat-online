<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_page_renders_core_archive_sections(): void
    {
        $response = $this->get(route('events.index'));

        $response->assertOk();
        $response->assertSee('Discover local events that are tied to real businesses and built for Eastern Freestate visibility.');
        $response->assertSee('Event Results');
        $response->assertSee('Browse Event Categories');
        $response->assertSee('Map view');
        $response->assertSee('Promote an event');
    }
}
