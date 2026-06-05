<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isSecure() && $this->shouldForce($request)) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        if ($response instanceof Response && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function shouldForce(Request $request): bool
    {
        if (app()->environment('local', 'testing')) {
            return false;
        }

        if (! (bool) env('APP_FORCE_HTTPS', config('app.force_https', true))) {
            return false;
        }

        if ($request->header('X-Forwarded-Proto') === 'https') {
            return false;
        }

        return true;
    }
}
