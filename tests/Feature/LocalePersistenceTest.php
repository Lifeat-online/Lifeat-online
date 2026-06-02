<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_language_choice_is_saved_to_cookie(): void
    {
        $response = $this->post(route('locale.switch', 'af'));

        $response->assertRedirect();
        $response->assertCookie('locale', 'af');
        $this->assertSame('af', session('locale'));
    }

    public function test_user_language_choice_is_saved_to_account_and_cookie(): void
    {
        $user = User::factory()->create(['preferred_locale' => null]);

        $response = $this
            ->actingAs($user)
            ->post(route('locale.switch', 'af'));

        $response->assertRedirect();
        $response->assertCookie('locale', 'af');
        $this->assertSame('af', $user->fresh()->preferred_locale);
        $this->assertSame('af', session('locale'));
    }

    public function test_locale_middleware_prefers_user_locale_before_cookie(): void
    {
        Route::middleware(['web', SetLocale::class])
            ->get('/locale-persistence-test', function () {
                return response(App::getLocale());
            });

        $user = User::factory()->create(['preferred_locale' => 'af']);

        $response = $this
            ->actingAs($user)
            ->withCookie('locale', 'en')
            ->get('/locale-persistence-test');

        $response->assertOk();
        $response->assertSee('af');
    }

    public function test_locale_middleware_prefers_user_locale_before_stale_session(): void
    {
        Route::middleware(['web', SetLocale::class])
            ->get('/locale-stale-session-test', function () {
                return response(App::getLocale());
            });

        $user = User::factory()->create(['preferred_locale' => 'af']);

        $response = $this
            ->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->withCookie('locale', 'en')
            ->get('/locale-stale-session-test');

        $response->assertOk();
        $response->assertSee('af');
        $this->assertSame('af', session('locale'));
    }

    public function test_guest_cookie_locale_wins_over_stale_session_locale(): void
    {
        Route::middleware(['web', SetLocale::class])
            ->get('/locale-cookie-session-test', function () {
                return response(App::getLocale());
            });

        $response = $this
            ->withSession(['locale' => 'en'])
            ->withCookie('locale', 'af')
            ->get('/locale-cookie-session-test');

        $response->assertOk();
        $response->assertSee('af');
        $this->assertSame('af', session('locale'));
    }

    public function test_login_uses_saved_account_locale_and_refreshes_cookie(): void
    {
        $user = User::factory()->create(['preferred_locale' => 'af']);

        $response = $this
            ->withCookie('locale', 'en')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookie('locale', 'af');
        $this->assertSame('af', session('locale'));
    }

    public function test_login_backfills_missing_account_locale_from_cookie(): void
    {
        $user = User::factory()->create(['preferred_locale' => null]);

        $response = $this
            ->withCookie('locale', 'af')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookie('locale', 'af');
        $this->assertSame('af', $user->fresh()->preferred_locale);
    }

    public function test_registration_saves_cookie_locale_to_new_profile(): void
    {
        $response = $this
            ->withCookie('locale', 'af')
            ->post('/register', [
                'name' => 'Afrikaans User',
                'email' => 'afrikaans@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookie('locale', 'af');
        $this->assertDatabaseHas('users', [
            'email' => 'afrikaans@example.com',
            'preferred_locale' => 'af',
        ]);
    }
}
