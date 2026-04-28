<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_core_front_page_sections(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Local news, trusted businesses, community events, and space to promote what matters.');
        $response->assertSee('Top Story and Latest News');
        $response->assertSee('Featured Businesses');
        $response->assertSee('Upcoming Events');
        $response->assertSee('Community marketplace');
    }
}
