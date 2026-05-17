<?php

namespace App\Http\Middleware;

use App\Models\TransportDutySession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTransportDriverOnDuty
{
    public function handle(Request $request, Closure $next): Response
    {
        $driver = $request->user()?->transportDriver()
            ->with('activeDutySession')
            ->first();

        $session = $driver?->activeDutySession;

        if (! $driver?->isApproved() || ! $session || ! in_array($session->status, [
            TransportDutySession::STATUS_AVAILABLE,
            TransportDutySession::STATUS_BUSY,
        ], true)) {
            return redirect()->route('transport.driver.duty')
                ->with('status', 'Clock in as available before opening the live driver workspace.');
        }

        return $next($request);
    }
}
