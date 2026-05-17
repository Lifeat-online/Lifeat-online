<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CivicFaultMapPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_fault_map_filters_render_in_dropdown_panel(): void
    {
        $response = $this->get(route('faults.index'));

        $response->assertOk();
        $response->assertSee('DA Civic Infrastructure Fault Reporting');
        $response->assertSee('id="fault-filter-dropdown"', false);
        $response->assertSee('id="fault-filter-count"', false);
        $response->assertSee('id="fault-filter-reset"', false);
        $response->assertSee('Category');
        $response->assertSee('Councillor');
    }
}
