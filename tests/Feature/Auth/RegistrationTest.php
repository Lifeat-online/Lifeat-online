<?php

namespace Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_users_can_register_when_legacy_users_table_is_missing_role_and_timestamps(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'created_at', 'updated_at']);
        });

        try {
            $response = $this->post('/register', [
                'name' => 'Legacy User',
                'email' => 'legacy@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $this->assertAuthenticated();
            $response->assertRedirect(route('dashboard', absolute: false));
            $this->assertDatabaseHas('users', [
                'email' => 'legacy@example.com',
            ]);
        } finally {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'role')) {
                    $table->string('role')->default('member')->after('password');
                }

                if (! Schema::hasColumn('users', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }
}
