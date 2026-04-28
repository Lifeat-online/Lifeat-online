<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_page_renders_core_archive_sections(): void
    {
        $response = $this->get(route('directory.index'));

        $response->assertOk();
        $response->assertSee('Discover trusted Eastern Freestate businesses, ranked for visibility and built to convert local attention.');
        $response->assertSee('Directory Results');
        $response->assertSee('Browse Categories');
        $response->assertSee('Map view');
        $response->assertSee('Get listed');
    }
}
