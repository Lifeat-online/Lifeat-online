<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_endpoint_is_disabled_by_default(): void
    {
        $this->postJson(route('bootstrap.admin'))->assertStatus(404);
    }

    public function test_bootstrap_endpoint_creates_admin_and_logs_in(): void
    {
        config([
            'app.railway_admin_bootstrap_enabled' => true,
            'app.railway_admin_bootstrap_name' => 'Railway Admin',
            'app.railway_admin_bootstrap_email' => 'railway-admin@example.com',
            'app.railway_admin_bootstrap_password' => 'Password123!',
        ]);

        $response = $this->postJson(route('bootstrap.admin'));

        $response
            ->assertOk()
            ->assertJson([
                'ok' => true,
            ]);

        $this->assertAuthenticated();

        $user = User::query()->where('email', 'railway-admin@example.com')->firstOrFail();
        $this->assertSame('super_admin', $user->role);
    }
}

