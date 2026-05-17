<?php

namespace Tests\Feature;

use App\Models\WriterApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffSignupPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_signup_page_renders_core_application_sections(): void
    {
        $response = $this->get(route('staff-signup.create'));

        $response->assertOk();
        $response->assertSee('Writer and staff application');
        $response->assertSee('Sample Content');
        $response->assertSee('Banking And Verification');
        $response->assertSee('Use this if businesses may contact you on WhatsApp after your staff application is approved.');
        $response->assertSee('Submit staff application');
    }

    public function test_visitor_can_submit_a_staff_signup_application(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $response = $this->post(route('staff-signup.store'), [
            'first_name' => 'Lebo',
            'last_name' => 'Mokoena',
            'email' => 'lebo@example.com',
            'phone' => '082 000 0000',
            'username' => 'lebo_writer',
            'profile_bio' => str_repeat('I write community-focused stories and enjoy helping local businesses communicate clearly. ', 2),
            'available_on_whatsapp' => '1',
            'sample_article_title' => 'How the Saturday market is helping small Bethlehem traders',
            'sample_article_body' => str_repeat('This article sample explores how local traders use the Saturday market to build repeat customers and create opportunities in the region. ', 4),
            'sample_advert_title' => 'Visit Blue Crane Bakery this weekend',
            'sample_advert_body' => str_repeat('Fresh bread, family specials, and a warm local welcome make this bakery worth the stop. ', 2),
            'bank_name' => 'Capitec',
            'account_holder_name' => 'Lebo Mokoena',
            'account_number' => '1234567890',
            'branch_code' => '470010',
            'profile_photo_upload' => UploadedFile::fake()->image('profile.jpg'),
            'id_document_upload' => UploadedFile::fake()->create('id.pdf', 120, 'application/pdf'),
            'banking_document_upload' => UploadedFile::fake()->create('banking.pdf', 120, 'application/pdf'),
            'proof_of_residence_upload' => UploadedFile::fake()->create('residence.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('staff-signup.submitted'));

        $this->assertDatabaseHas('writer_applications', [
            'email' => 'lebo@example.com',
            'username' => 'lebo_writer',
            'status' => 'pending',
            'available_on_whatsapp' => true,
        ]);

        $application = WriterApplication::query()->firstOrFail();

        Storage::disk('public')->assertExists($application->profile_photo_path);
        Storage::disk('local')->assertExists($application->id_document_path);
        Storage::disk('local')->assertExists($application->banking_document_path);
        Storage::disk('local')->assertExists($application->proof_of_residence_path);
        Storage::disk('public')->assertMissing($application->id_document_path);
        Storage::disk('public')->assertMissing($application->banking_document_path);
        Storage::disk('public')->assertMissing($application->proof_of_residence_path);

        $this->followRedirects($response)
            ->assertSee('Application received')
            ->assertSee('lebo@example.com');
    }
}
