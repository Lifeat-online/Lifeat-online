<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\GoogleMapsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapIntegrationController extends Controller
{
    public function saveKey(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $this->ensureDevOwner($request);

        $validated = $request->validate([
            'google_maps_api_key' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'maps.google_api_key'],
            [
                'value' => trim($validated['google_maps_api_key']),
                'type' => 'secret',
                'group' => 'maps',
                'updated_by_user_id' => $request->user()->id,
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'Google Maps key saved.',
            'configured' => $maps->configured(),
            'source' => $maps->apiKeySource(),
            'masked_key' => $maps->maskedApiKey(),
        ]);
    }

    private function ensureDevOwner(Request $request): void
    {
        if (strtolower((string) $request->user()?->email) !== 'jameskoen78@gmail.com') {
            abort(403);
        }
    }
}
