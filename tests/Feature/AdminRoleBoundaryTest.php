<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\CivicFaultReport;
use App\Models\Classified;
use App\Models\Councillor;
use App\Models\Event;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_user_is_denied_management_api_surfaces(): void
    {
        $support = User::factory()->create(['role' => 'support']);
        $owner = User::factory()->create();

        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'draft',
            'published_at' => null,
        ]);

        $event = Event::create([
            'user_id' => $owner->id,
            'listing_id' => $listing->id,
            'title' => 'Boundary Event',
            'slug' => 'boundary-event-'.Str::lower(Str::random(6)),
            'start_at' => now()->addDay(),
            'status' => 'draft',
        ]);

        $article = Article::create([
            'user_id' => $owner->id,
            'title' => 'Boundary Article',
            'slug' => 'boundary-article-'.Str::lower(Str::random(6)),
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        foreach ([
            route('api.admin.listings.index'),
            route('api.admin.listings.show', $listing),
            route('api.admin.events.index'),
            route('api.admin.events.show', $event),
            route('api.admin.articles.index'),
            route('api.admin.articles.show', $article),
        ] as $uri) {
            $this->actingAs($support)->getJson($uri)->assertForbidden();
        }

        $this->actingAs($support)->postJson(route('api.admin.listings.store'), [])->assertForbidden();
        $this->actingAs($support)->putJson(route('api.admin.listings.update', $listing), [])->assertForbidden();
        $this->actingAs($support)->deleteJson(route('api.admin.listings.destroy', $listing))->assertForbidden();
        $this->actingAs($support)->postJson(route('api.admin.listings.bulk'), [
            'ids' => [$listing->slug],
            'action' => 'publish',
        ])->assertForbidden();

        $this->actingAs($support)->postJson(route('api.admin.events.store'), [])->assertForbidden();
        $this->actingAs($support)->putJson(route('api.admin.events.update', $event), [])->assertForbidden();
        $this->actingAs($support)->deleteJson(route('api.admin.events.destroy', $event))->assertForbidden();
        $this->actingAs($support)->postJson(route('api.admin.events.bulk'), [
            'ids' => [$event->slug],
            'action' => 'publish',
        ])->assertForbidden();
    }

    public function test_staff_user_is_denied_article_moderation_api_reads(): void
    {
        $staff = User::factory()->create(['role' => 'sales_staff']);
        $writer = User::factory()->create(['role' => 'writer']);

        $article = Article::create([
            'user_id' => $writer->id,
            'title' => 'Staff Hidden Draft',
            'slug' => 'staff-hidden-draft-'.Str::lower(Str::random(6)),
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        $this->actingAs($staff)->getJson(route('api.admin.articles.index'))->assertForbidden();
        $this->actingAs($staff)->getJson(route('api.admin.articles.show', $article))->assertForbidden();
    }

    public function test_writer_routes_and_admin_writer_surfaces_are_separated(): void
    {
        $writer = User::factory()->create(['role' => 'writer']);
        $support = User::factory()->create(['role' => 'support']);

        $this->actingAs($writer)->get(route('writer.articles.index'))->assertOk();
        $this->actingAs($support)->get(route('writer.articles.index'))->assertForbidden();

        $this->actingAs($support)->get(route('admin.writer-applications.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.writer-payments.index'))->assertForbidden();
        $this->actingAs($writer)->get(route('admin.writer-applications.index'))->assertForbidden();
    }

    public function test_support_user_cannot_review_classifieds(): void
    {
        $support = User::factory()->create(['role' => 'support']);
        $owner = User::factory()->create();

        $classified = Classified::create([
            'user_id' => $owner->id,
            'title' => 'Moderation Boundary Classified',
            'slug' => 'moderation-boundary-classified-'.Str::lower(Str::random(6)),
            'description' => 'Pending classified for role-boundary coverage.',
            'currency' => 'ZAR',
            'status' => Classified::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->actingAs($support)->get(route('admin.classifieds.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.classifieds.show', $classified))->assertForbidden();
        $this->actingAs($support)->post(route('admin.classifieds.review', $classified), [
            'status' => Classified::STATUS_PUBLISHED,
        ])->assertForbidden();
    }

    public function test_councillor_workspace_is_separate_from_admin_civic_moderation(): void
    {
        $councillorUser = User::factory()->create(['role' => 'councillor']);
        $support = User::factory()->create(['role' => 'support']);
        $editor = User::factory()->create(['role' => 'content_manager']);
        $reporter = User::factory()->create();

        $councillor = Councillor::create([
            'user_id' => $councillorUser->id,
            'full_name' => 'Boundary Councillor',
            'portfolios' => [],
            'category_responsibilities' => [],
            'is_active' => true,
        ]);

        $report = CivicFaultReport::create([
            'reporter_user_id' => $reporter->id,
            'assigned_councillor_id' => $councillor->id,
            'category' => 'pothole',
            'severity' => CivicFaultReport::SEVERITY_MEDIUM,
            'status' => CivicFaultReport::STATUS_REPORTED,
            'address_label' => 'Boundary Street',
            'latitude' => -28.2311,
            'longitude' => 28.3078,
            'description' => 'Boundary report.',
            'consented_at' => now(),
            'is_approved' => true,
        ]);

        $this->actingAs($councillorUser)->get(route('councillor.faults.index'))->assertOk();
        $this->actingAs($support)->get(route('councillor.faults.index'))->assertForbidden();
        $this->actingAs($councillorUser)->get(route('admin.fault-reports.index'))->assertForbidden();

        $this->actingAs($support)->getJson(route('api.admin.fault-reports.index'))->assertForbidden();
        $this->actingAs($support)->postJson(route('api.admin.fault-reports.moderate', $report), [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->actingAs($editor)->get(route('admin.councillors.index'))->assertForbidden();
        $this->actingAs($editor)->getJson(route('api.admin.councillors.index'))->assertForbidden();
    }
}
