<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_page_is_public_and_contains_core_privacy_sections(): void
    {
        $response = $this->get(route('legal.privacy'));

        $response->assertOk();
        $response->assertSee('Privacy Policy');
        $response->assertSee('Information We Collect');
        $response->assertSee('Payments and Billing Data');
        $response->assertSee('Access Control and Auditability');
        $response->assertSee(route('legal.terms'), false);
    }

    public function test_account_page_links_to_privacy_policy(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk();
        $response->assertSee(route('legal.privacy'), false);
        $response->assertSee('Privacy policy');
    }
}
