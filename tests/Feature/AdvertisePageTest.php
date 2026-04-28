<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvertisePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_advertise_page_renders_monetisation_ladder_and_live_package_cards(): void
    {
        $response = $this->get(route('advertise.index'));

        $response->assertOk();
        $response->assertSee('Advertise With Us');
        $response->assertSee('Start with a business directory package');
        $response->assertSee('Directory Packages');
        $response->assertSee('Business Directory Standard');
        $response->assertSee('Business Directory Self-Service');
        $response->assertSee('Event Add-Ons');
        $response->assertSee('Event One-Off Package');
        $response->assertSee('Campaign Expansion');
        $response->assertSee('Push Campaigns');
    }
}
