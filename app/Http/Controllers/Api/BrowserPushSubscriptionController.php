<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrowserPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrowserPushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
        ]);

        $subscription = BrowserPushSubscription::updateOrCreate(
            ['endpoint_hash' => BrowserPushSubscription::endpointHash($validated['endpoint'])],
            [
                'user_id' => $request->user()?->id,
                'endpoint' => $validated['endpoint'],
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_seen_at' => now(),
                'revoked_at' => null,
                'failure_count' => 0,
            ]
        );

        return response()->json([
            'status' => 'subscribed',
            'id' => $subscription->id,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
        ]);

        BrowserPushSubscription::where('endpoint_hash', BrowserPushSubscription::endpointHash($validated['endpoint']))
            ->update(['revoked_at' => now()]);

        return response()->json([
            'status' => 'unsubscribed',
        ]);
    }
}
