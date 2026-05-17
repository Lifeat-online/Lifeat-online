<?php

namespace App\Http\Controllers;

use App\Services\GoogleMapsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapAddressController extends Controller
{
    public function autocomplete(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-35,-22'],
            'lng' => ['nullable', 'numeric', 'between:16,33'],
        ]);

        if (! $maps->configured()) {
            return response()->json([
                'ok' => false,
                'configured' => false,
                'provider' => 'google',
                'message' => 'Google Maps is not configured.',
                'results' => [],
            ]);
        }

        return response()->json([
            'ok' => true,
            'configured' => true,
            'provider' => 'google',
            'results' => $maps->autocomplete(
                $validated['q'],
                isset($validated['lat']) ? (float) $validated['lat'] : null,
                isset($validated['lng']) ? (float) $validated['lng'] : null,
            ),
        ]);
    }

    public function place(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $validated = $request->validate([
            'place_id' => ['required', 'string', 'max:255'],
        ]);

        if (! $maps->configured()) {
            return response()->json([
                'ok' => false,
                'configured' => false,
                'provider' => 'google',
                'message' => 'Google Maps is not configured.',
                'result' => null,
            ]);
        }

        $result = $maps->place($validated['place_id']);

        return response()->json([
            'ok' => $result !== null,
            'configured' => true,
            'provider' => 'google',
            'message' => $result === null ? 'Address details were not found.' : 'Address details found.',
            'result' => $result,
        ]);
    }

    public function reverse(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-35,-22'],
            'lng' => ['required', 'numeric', 'between:16,33'],
        ]);

        if (! $maps->configured()) {
            return response()->json([
                'ok' => false,
                'configured' => false,
                'provider' => 'google',
                'message' => 'Google Maps is not configured.',
                'address' => null,
            ]);
        }

        $address = $maps->reverse((float) $validated['lat'], (float) $validated['lng']);

        return response()->json([
            'ok' => $address !== null,
            'configured' => true,
            'provider' => 'google',
            'message' => $address === null ? 'Address was not found.' : 'Address found.',
            'address' => $address,
        ]);
    }
}
