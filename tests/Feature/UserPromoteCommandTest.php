<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPromoteCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotes_a_user_to_dev_role(): void
    {
        $user = User::factory()->create(['role' => 'registered_user']);

        $this->artisan('user:promote', [
            '--email' => $user->email,
            '--role' => 'dev',
        ])->assertExitCode(0);

        $this->assertSame('dev', $user->fresh()->role);
    }

    public function test_assigns_pivot_role_when_role_table_exists(): void
    {
        $devRole = Role::create(['slug' => 'dev', 'name' => 'Developer']);
        $user = User::factory()->create(['role' => 'registered_user']);

        $this->artisan('user:promote', [
            '--email' => $user->email,
            '--role' => 'dev',
        ])->assertExitCode(0);

        $this->assertTrue($user->fresh()->roles()->where('slug', 'dev')->exists());
    }

    public function test_dry_run_does_not_modify_user(): void
    {
        $user = User::factory()->create(['role' => 'registered_user']);

        $this->artisan('user:promote', [
            '--email' => $user->email,
            '--role' => 'dev',
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame('registered_user', $user->fresh()->role);
    }

    public function test_no_op_when_user_already_on_target_role(): void
    {
        $user = User::factory()->create(['role' => 'dev']);

        $this->artisan('user:promote', [
            '--email' => $user->email,
            '--role' => 'dev',
        ])->assertExitCode(0);

        $this->assertSame('dev', $user->fresh()->role);
    }

    public function test_fails_for_unknown_email(): void
    {
        $this->artisan('user:promote', [
            '--email' => 'nope@example.test',
            '--role' => 'dev',
        ])->assertExitCode(1);
    }

    public function test_fails_for_invalid_email(): void
    {
        $this->artisan('user:promote', [
            '--email' => 'not-an-email',
            '--role' => 'dev',
        ])->assertExitCode(1);
    }

    public function test_list_option_does_not_modify_users(): void
    {
        $user = User::factory()->create(['role' => 'registered_user']);

        $this->artisan('user:promote', ['--list' => true])->assertExitCode(0);

        $this->assertSame('registered_user', $user->fresh()->role);
    }

    public function test_writes_audit_log_with_before_and_after(): void
    {
        $user = User::factory()->create(['role' => 'registered_user']);

        $this->artisan('user:promote', [
            '--email' => $user->email,
            '--role' => 'dev',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'action' => 'user.role_promoted',
        ]);
    }
}
