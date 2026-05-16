<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevToolsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('DEV_TOOLS_ENABLED');
        putenv('DEV_TEST_RUNNER_ENABLED');

        parent::tearDown();
    }

    public function test_super_admin_can_use_dev_test_runner_in_testing_environment(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->postJson(route('dev.tests.run'), ['suite' => 'Bogus'])
            ->assertUnprocessable();
    }

    public function test_dev_test_runner_is_blocked_in_production_unless_explicitly_enabled(): void
    {
        config(['app.env' => 'production']);
        putenv('DEV_TOOLS_ENABLED=false');

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->postJson(route('dev.tests.run'), ['suite' => 'Unit'])
            ->assertForbidden();
    }

    public function test_dev_tab_is_hidden_when_dev_tools_are_disabled(): void
    {
        config(['app.env' => 'production']);
        putenv('DEV_TOOLS_ENABLED=false');

        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Developer Control Center');
        $response->assertDontSee('Testing Area');
    }

    public function test_test_runner_panel_is_hidden_when_runner_is_disabled_in_production(): void
    {
        config(['app.env' => 'production']);
        putenv('DEV_TOOLS_ENABLED=true');
        putenv('DEV_TEST_RUNNER_ENABLED=false');

        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Developer Control Center');
        $response->assertDontSee('Testing Area');
        $response->assertDontSee(route('dev.tests.run'));
    }
}
