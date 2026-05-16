<?php

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadOrphansCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_orphans_command_reports_and_deletes_unreferenced_public_files(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('listings/featured/referenced.jpg', 'referenced');
        Storage::disk('public')->put('listings/featured/orphan.jpg', 'orphan');

        Listing::create([
            'title' => 'Referenced Listing',
            'slug' => 'referenced-listing',
            'status' => 'draft',
            'featured_image' => 'listings/featured/referenced.jpg',
        ]);

        $this->artisan('uploads:orphans --disk=public')
            ->expectsOutput('listings/featured/orphan.jpg')
            ->assertExitCode(1);

        $this->artisan('uploads:orphans --disk=public --delete')
            ->expectsOutput('listings/featured/orphan.jpg')
            ->assertExitCode(0);

        Storage::disk('public')->assertExists('listings/featured/referenced.jpg');
        Storage::disk('public')->assertMissing('listings/featured/orphan.jpg');
    }
}
