<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CivicFaultReport;
use App\Models\Councillor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCivicFaultWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_moderate_assign_and_resolve_fault_via_api_workflow(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $reporter = User::factory()->create();

        $councillor = Councillor::create([
            'full_name' => 'Ward Councillor',
            'phone' => null,
            'email' => null,
            'office_address' => null,
            'portfolios' => [],
            'category_responsibilities' => [],
            'is_active' => true,
        ]);

        $report = CivicFaultReport::create([
            'reporter_user_id' => $reporter->id,
            'category' => 'pothole',
            'severity' => CivicFaultReport::SEVERITY_MEDIUM,
            'status' => CivicFaultReport::STATUS_REPORTED,
            'address_label' => 'Test Street',
            'latitude' => -33.9249,
            'longitude' => 18.4241,
            'description' => 'Large pothole near the stop sign.',
            'consented_at' => now(),
            'is_approved' => false,
        ]);

        $moderate = $this->actingAs($admin)->postJson(route('api.admin.fault-reports.moderate', $report), [
            'decision' => 'approve',
        ]);
        $moderate->assertOk();
        $this->assertTrue((bool) $report->fresh()->is_approved);

        $update = $this->actingAs($admin)->putJson(route('api.admin.fault-reports.update', $report), [
            'status' => CivicFaultReport::STATUS_IN_PROGRESS,
            'assigned_councillor_id' => $councillor->id,
        ]);
        $update->assertOk();
        $this->assertSame(CivicFaultReport::STATUS_IN_PROGRESS, $report->fresh()->status);
        $this->assertNotNull($report->fresh()->in_progress_at);
        $this->assertSame($councillor->id, $report->fresh()->assigned_councillor_id);

        $bulk = $this->actingAs($admin)->postJson(route('api.admin.fault-reports.bulk'), [
            'ids' => [$report->id],
            'action' => 'set_status',
            'status' => CivicFaultReport::STATUS_RESOLVED,
        ]);
        $bulk->assertOk();
        $this->assertSame(CivicFaultReport::STATUS_RESOLVED, $report->fresh()->status);
        $this->assertNotNull($report->fresh()->resolved_at);

        $this->assertTrue(AuditLog::where('action', 'civic_fault_report.moderated')->exists());
        $this->assertTrue(AuditLog::where('action', 'civic_fault_report.updated')->exists());
        $this->assertTrue(AuditLog::where('action', 'civic_fault_report.bulk_status_updated')->exists());
    }
}
