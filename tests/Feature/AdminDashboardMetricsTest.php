<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_metrics_json(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.metrics'));

        $response->assertOk();
        $response->assertJsonStructure([
            'faults' => ['pending', 'approved', 'reported', 'in_progress', 'resolved', 'reported_last_hour', 'resolved_last_7d', 'avg_resolution_hours_last_50'],
            'councillors' => ['active', 'inactive'],
            'advertising' => ['ads_active', 'ads_ready', 'push_pending'],
            'integrations' => ['total', 'active'],
            'core' => ['listings', 'vouchers'],
        ]);
    }

    public function test_support_user_cannot_access_listing_management_routes(): void
    {
        $support = User::factory()->create([
            'role' => 'support',
        ]);

        $this->actingAs($support)->get(route('admin.listings.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.events.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.articles.index'))->assertForbidden();
    }
}
