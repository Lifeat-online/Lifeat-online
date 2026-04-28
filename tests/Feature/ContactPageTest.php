<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_is_public_and_contains_support_channels(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertOk();
        $response->assertSee('Contact Us');
        $response->assertSee('Email support');
        $response->assertSee('Phone');
        $response->assertSee('WhatsApp');
        $response->assertSee('Helpful Pages First');
    }

    public function test_terms_page_links_to_contact_support(): void
    {
        $response = $this->get(route('legal.terms'));

        $response->assertOk();
        $response->assertSee(route('contact.index'), false);
        $response->assertSee('Contact support');
    }
}
