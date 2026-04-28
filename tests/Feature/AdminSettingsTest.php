<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_settings_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('Platform Settings');
    }

    public function test_admin_can_update_settings_and_create_audit_log(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'settings' => [
                'pricing.business_directory_6m' => '650.00',
                'billing.invoice_prefix' => 'LFE',
            ],
        ]);

        $response->assertRedirect(route('admin.settings.index'));

        $this->assertSame('650.00', Setting::getValue('pricing.business_directory_6m'));
        $this->assertSame('LFE', Setting::getValue('billing.invoice_prefix'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'setting.updated',
            'subject_type' => Setting::class,
        ]);
    }

    public function test_non_admin_user_cannot_access_settings_page(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
        ]);

        $response = $this->actingAs($member)->get(route('admin.settings.index'));

        $response->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
