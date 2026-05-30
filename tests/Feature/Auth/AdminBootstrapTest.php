<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bootstrap_is_hidden_without_configured_token_outside_local(): void
    {
        $this->postJson('/__bootstrap/admin')->assertNotFound();
    }

    public function test_admin_bootstrap_can_recreate_dev_owner_with_configured_token(): void
    {
        config(['app.admin_bootstrap_token' => 'test-bootstrap-token']);

        $response = $this
            ->withHeader('X-Admin-Bootstrap-Token', 'test-bootstrap-token')
            ->postJson('/__bootstrap/admin');

        $response
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'redirect' => route('admin.dashboard'),
            ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'jameskoen78@gmail.com',
            'role' => 'super_admin',
        ]);
        $this->assertTrue(User::where('email', 'jameskoen78@gmail.com')->firstOrFail()->hasRole('dev'));
    }
}
