<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMallVendorIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->user()?->mallStore;

        if (! $store || $store->status !== 'active') {
            abort(403);
        }

        return $next($request);
    }
}
