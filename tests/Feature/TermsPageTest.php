<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_is_public_and_contains_payment_trust_copy(): void
    {
        $response = $this->get(route('legal.terms'));

        $response->assertOk();
        $response->assertSee('Terms and Conditions');
        $response->assertSee('Directory-First Rule');
        $response->assertSee('Payments and Invoices');
        $response->assertSee('Privacy and Compliance');
        $response->assertSee('Start listing');
    }

    public function test_checkout_page_links_to_terms_before_purchase(): void
    {
        $response = $this->get(route('checkout.index'));

        $response->assertOk();
        $response->assertSee(route('legal.terms'), false);
        $response->assertSee('Read the terms and conditions');
    }
}
