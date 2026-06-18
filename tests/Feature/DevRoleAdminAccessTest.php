<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevRoleAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_user_returns_true_for_admin(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $this->assertTrue($dev->hasRole('admin'));
    }

    public function test_dev_user_returns_true_for_super_admin(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $this->assertTrue($dev->hasRole('super_admin'));
    }

    public function test_dev_user_returns_true_for_editor(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $this->assertTrue($dev->hasRole('editor'));
    }

    public function test_dev_user_returns_true_for_combined_admin_editor(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $this->assertTrue($dev->hasRole('admin', 'editor'));
    }

    public function test_dev_user_is_not_a_support_staff(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $this->assertFalse($dev->hasRole('support'));
    }

    public function test_developer_user_returns_true_for_admin(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $this->assertTrue($dev->hasRole('admin'));
    }

    public function test_developer_user_returns_true_for_combined_admin_editor(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $this->assertTrue($dev->hasRole('admin', 'editor'));
    }

    public function test_regular_member_does_not_get_admin_via_dev_alias(): void
    {
        $member = User::factory()->create(['role' => 'registered_user']);
        $this->assertFalse($member->hasRole('admin'));
        $this->assertFalse($member->hasRole('admin', 'editor'));
    }

    public function test_dev_user_still_has_dev_role(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $this->assertTrue($dev->hasRole('dev'));
        $this->assertTrue($dev->hasRole('developer'));
    }
}
