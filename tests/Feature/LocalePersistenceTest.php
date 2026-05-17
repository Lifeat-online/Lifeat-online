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
}
