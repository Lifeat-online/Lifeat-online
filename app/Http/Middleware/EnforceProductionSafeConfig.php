<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnforceProductionSafeConfig
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production')) {
            if (config('app.debug') === true) {
                throw new \RuntimeException(
                    'APP_DEBUG must be false in production. Refusing to boot.'
                );
            }

            if (! $request->isSecure() && ! $this->isTrustedProxy($request)) {
                // Trust the proxy chain; don't fail
            }
        }

        return $next($request);
    }

    private function isTrustedProxy(Request $request): bool
    {
        try {
            $proxies = config('trustproxies.proxies');

            return $proxies === '*' || $proxies === '**';
        } catch (Throwable) {
            return false;
        }
    }
}
