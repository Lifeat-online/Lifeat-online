<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Classified;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassifiedModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_submit_classified_for_moderation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('classifieds.manage.store'), [
            'title' => 'Used Garden Tools',
            'description' => 'A full set of lightly used gardening tools.',
            'price' => 350,
            'currency' => 'ZAR',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'country' => 'South Africa',
        ]);

        $classified = Classified::firstOrFail();

        $response->assertRedirect(route('classifieds.manage.edit', $classified));

        $this->assertDatabaseHas('classifieds', [
            'user_id' => $user->id,
            'title' => 'Used Garden Tools',
            'status' => Classified::STATUS_PENDING,
        ]);

        $publicResponse = $this->get(route('classifieds.index'));
        $publicResponse->assertOk();
        $publicResponse->assertDontSee('Used Garden Tools');

        $manageResponse = $this->actingAs($user)->get(route('classifieds.manage.index'));
        $manageResponse->assertOk();
        $manageResponse->assertSee('Used Garden Tools');
        $manageResponse->assertSee('Pending');
    }

    public function test_admin_can_publish_pending_classified_and_make_it_public(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create();

        $classified = Classified::create([
            'user_id' => $owner->id,
            'title' => 'Second Hand Bicycle',
            'slug' => 'second-hand-bicycle',
            'description' => 'A commuter bicycle in good condition.',
            'currency' => 'ZAR',
            'status' => Classified::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.classifieds.review', $classified), [
            'status' => Classified::STATUS_PUBLISHED,
            'moderation_notes' => 'Approved for public display.',
        ]);

        $response->assertRedirect(route('admin.classifieds.show', $classified));

        $classified->refresh();

        $this->assertSame(Classified::STATUS_PUBLISHED, $classified->status);
        $this->assertSame($admin->id, $classified->reviewed_by_user_id);
        $this->assertNotNull($classified->published_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'classified.reviewed',
            'subject_type' => Classified::class,
            'subject_id' => $classified->id,
        ]);

        $audit = AuditLog::where('subject_type', Classified::class)->where('subject_id', $classified->id)->firstOrFail();
        $this->assertSame(Classified::STATUS_PUBLISHED, $audit->after_json['status'] ?? null);

        $publicIndex = $this->get(route('classifieds.index'));
        $publicIndex->assertOk();
        $publicIndex->assertSee('Second Hand Bicycle');

        $publicShow = $this->get(route('classifieds.show', $classified));
        $publicShow->assertOk();
        $publicShow->assertSee('Second Hand Bicycle');
    }

    public function test_flagged_classified_remains_hidden_from_public_view(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create();

        $classified = Classified::create([
            'user_id' => $owner->id,
            'title' => 'Bulk Mobile Phones',
            'slug' => 'bulk-mobile-phones',
            'description' => 'Suspicious listing under review.',
            'currency' => 'ZAR',
            'status' => Classified::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.classifieds.review', $classified), [
            'status' => Classified::STATUS_FLAGGED,
            'moderation_notes' => 'Needs manual verification.',
        ])->assertRedirect(route('admin.classifieds.show', $classified));

        $classified->refresh();

        $this->assertSame(Classified::STATUS_FLAGGED, $classified->status);
        $this->assertNull($classified->published_at);

        $publicIndex = $this->get(route('classifieds.index'));
        $publicIndex->assertOk();
        $publicIndex->assertDontSee('Bulk Mobile Phones');

        $publicShow = $this->get(route('classifieds.show', $classified));
        $publicShow->assertNotFound();
    }
}
