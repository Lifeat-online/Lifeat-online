<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCreateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_create_command_creates_admin_and_writes_audit_log(): void
    {
        $this->artisan('admin:create', [
            '--email' => 'ops-admin@example.test',
            '--name' => 'Ops Admin',
            '--password' => 'launch-ready-password',
            '--role' => 'super_admin',
        ])->assertExitCode(0);

        $user = User::where('email', 'ops-admin@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('admin'));

        $log = AuditLog::where('action', 'admin_account.created')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->firstOrFail();

        $this->assertSame([], $log->before_json);
        $this->assertSame('super_admin', $log->after_json['primary_role']);
        $this->assertSame('super_admin', $log->after_json['requested_role']);
        $this->assertContains('super_admin', $log->after_json['assigned_role_slugs']);
        $this->assertArrayNotHasKey('password', $log->after_json);
        $this->assertSame('artisan admin:create', $log->user_agent);
    }

    public function test_admin_create_command_audits_existing_user_promotion(): void
    {
        $user = User::factory()->create([
            'email' => 'existing-user@example.test',
            'name' => 'Existing User',
            'role' => 'member',
        ]);

        $this->artisan('admin:create', [
            '--email' => 'existing-user@example.test',
            '--name' => 'Existing Admin',
            '--password' => 'new-launch-password',
            '--role' => 'super_admin',
        ])->assertExitCode(0);

        $user->refresh();
        $this->assertSame('Existing Admin', $user->name);
        $this->assertSame('super_admin', $user->role);
        $this->assertTrue($user->hasRole('admin'));

        $log = AuditLog::where('action', 'admin_account.role_changed')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->firstOrFail();

        $this->assertSame('member', $log->before_json['primary_role']);
        $this->assertSame('super_admin', $log->after_json['primary_role']);
        $this->assertSame('super_admin', $log->after_json['requested_role']);
        $this->assertArrayNotHasKey('password', $log->before_json);
        $this->assertArrayNotHasKey('password', $log->after_json);
    }
}
