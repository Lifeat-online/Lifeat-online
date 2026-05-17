<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleMapsService
{
    public function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function apiKeySource(): string
    {
        if ((string) config('services.google_maps.key') !== '') {
            return 'Environment';
        }

        if ((string) Setting::getValue('maps.google_api_key', '') !== '') {
            return 'Settings';
        }

        return 'Missing';
    }

    public function maskedApiKey(): string
    {
        $key = $this->apiKey();

        if ($key === '') {
            return '';
        }

        if (Str::length($key) <= 10) {
            return Str::mask($key, '*', 2);
        }

        return Str::substr($key, 0, 6).'...'.Str::substr($key, -4);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function autocomplete(string $input, ?float $lat = null, ?float $lng = null): array
    {
        if (! $this->configured()) {
            return [];
        }

        $payload = [
            'input' => $input,
            'includedRegionCodes' => ['za'],
        ];

        if ($lat !== null && $lng !== null) {
            $payload['locationBias'] = [
                'circle' => [
                    'center' => [
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ],
                    'radius' => 50000.0,
                ],
            ];
        }

        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Goog-Api-Key' => $this->apiKey(),
                    'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text,suggestions.placePrediction.structuredFormat',
                ])
                ->post($this->placesEndpoint().'/places:autocomplete', $payload);

            if (! $response->successful()) {
                $this->logFailure('Google Places autocomplete failed', $response->status(), $response->body());

                return [];
            }

            return collect($response->json('suggestions', []))
                ->map(fn (array $suggestion): ?array => $this->mapSuggestion($suggestion))
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('Google Places autocomplete request failed.', ['message' => $exception->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function place(string $placeId): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Goog-Api-Key' => $this->apiKey(),
                    'X-Goog-FieldMask' => 'id,formattedAddress,location,displayName,addressComponents',
                ])
                ->get($this->placesEndpoint().'/places/'.rawurlencode($placeId));

            if (! $response->successful()) {
                $this->logFailure('Google Place Details failed', $response->status(), $response->body());

                return null;
            }

            $place = $response->json();
            $location = $place['location'] ?? [];
            $lat = $location['latitude'] ?? null;
            $lng = $location['longitude'] ?? null;

            if (! is_numeric($lat) || ! is_numeric($lng)) {
                return null;
            }

            $address = (string) ($place['formattedAddress'] ?? '');
            $name = (string) data_get($place, 'displayName.text', '');

            return [
                'place_id' => (string) ($place['id'] ?? $placeId),
                'label' => $name !== '' ? $name : ($address !== '' ? $address : 'Address'),
                'display_name' => $address !== '' ? $address : $name,
                'formatted_address' => $address,
                'lat' => (float) $lat,
                'lon' => (float) $lng,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Google Place Details request failed.', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    public function reverse(float $lat, float $lng): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->get($this->geocodingEndpoint(), [
                    'latlng' => $lat.','.$lng,
                    'key' => $this->apiKey(),
                    'result_type' => 'street_address|premise|subpremise|route',
                ]);

            if (! $response->successful() || $response->json('status') !== 'OK') {
                $this->logFailure('Google reverse geocoding failed', $response->status(), $response->body());

                return null;
            }

            return (string) data_get($response->json(), 'results.0.formatted_address') ?: null;
        } catch (\Throwable $exception) {
            Log::warning('Google reverse geocoding request failed.', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    private function mapSuggestion(array $suggestion): ?array
    {
        $prediction = $suggestion['placePrediction'] ?? null;

        if (! is_array($prediction)) {
            return null;
        }

        $placeId = (string) ($prediction['placeId'] ?? '');
        $text = (string) data_get($prediction, 'text.text', '');
        $mainText = (string) data_get($prediction, 'structuredFormat.mainText.text', '');
        $secondaryText = (string) data_get($prediction, 'structuredFormat.secondaryText.text', '');

        if ($placeId === '' || $text === '') {
            return null;
        }

        return [
            'place_id' => $placeId,
            'label' => $mainText !== '' ? $mainText : $text,
            'detail' => $secondaryText,
            'display_name' => $text,
        ];
    }

    private function apiKey(): string
    {
        return trim((string) (config('services.google_maps.key') ?: Setting::getValue('maps.google_api_key', '')));
    }

    private function placesEndpoint(): string
    {
        return rtrim((string) config('services.google_maps.places_endpoint', 'https://places.googleapis.com/v1'), '/');
    }

    private function geocodingEndpoint(): string
    {
        return (string) config('services.google_maps.geocoding_endpoint', 'https://maps.googleapis.com/maps/api/geocode/json');
    }

    private function timeout(): int
    {
        return max(1, (int) config('services.google_maps.timeout', 15));
    }

    private function logFailure(string $message, int $status, string $body): void
    {
        Log::warning($message, [
            'status' => $status,
            'body' => Str::limit($body, 500),
        ]);
    }
}
