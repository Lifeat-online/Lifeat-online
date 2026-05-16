<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_public_routes_have_named_rate_limiters(): void
    {
        $this->assertRouteHasMiddleware('checkout.payfast.callback', 'throttle:payfast-callback');
        $this->assertRouteHasMiddleware('staff-signup.store', 'throttle:public-form');
        $this->assertRouteHasMiddleware('classifieds.manage.store', 'throttle:public-form');
        $this->assertRouteHasMiddleware('faults.report.store', 'throttle:public-form');
        $this->assertRouteHasMiddleware('vouchers.redeem', 'throttle:voucher-redemption');
        $this->assertRouteHasMiddleware('staff.vouchers.consume', 'throttle:voucher-redemption');
        $this->assertRouteHasMiddleware('ad-tracking.impression', 'throttle:public-tracking');
        $this->assertRouteHasMiddleware('ad-tracking.click', 'throttle:public-tracking');
        $this->assertRouteHasMiddleware('ad-tracking.push-open', 'throttle:public-tracking');
        $this->assertRouteForMethodHasMiddleware('POST', 'register', 'throttle:auth-sensitive');
        $this->assertRouteForMethodHasMiddleware('POST', 'login', 'throttle:auth-sensitive');
        $this->assertRouteHasMiddleware('password.email', 'throttle:auth-sensitive');
        $this->assertRouteHasMiddleware('password.store', 'throttle:auth-sensitive');
        $this->assertRouteHasMiddleware('password.update', 'throttle:auth-sensitive');
    }

    public function test_payfast_callback_is_rate_limited_by_ip(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->fromIp('203.0.113.10')
                ->post(route('checkout.payfast.callback'), [])
                ->assertStatus(302);
        }

        $this->fromIp('203.0.113.10')
            ->post(route('checkout.payfast.callback'), [])
            ->assertTooManyRequests();
    }

    private function assertRouteHasMiddleware(string $routeName, string $middleware): void
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "Route [{$routeName}] was not registered.");
        $this->assertContains($middleware, $route->gatherMiddleware(), "Route [{$routeName}] is missing [{$middleware}].");
    }

    private function assertRouteForMethodHasMiddleware(string $method, string $uri, string $middleware): void
    {
        $route = Route::getRoutes()->match(
            request()->create($uri, $method)
        );

        $this->assertContains($middleware, $route->gatherMiddleware(), "Route [{$method} {$uri}] is missing [{$middleware}].");
    }

    private function fromIp(string $ip): self
    {
        return $this->withServerVariables([
            'REMOTE_ADDR' => $ip,
        ]);
    }
}
