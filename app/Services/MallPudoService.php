<?php

namespace App\Services;

use App\Models\MallFulfillment;
use App\Models\MallOrder;
use App\Models\MallStore;
use App\Support\Mall\MallMoney;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MallPudoService
{
    public function configured(): bool
    {
        return trim((string) config('mall.pudo.api_key', '')) !== '';
    }

    public function lockers(?float $lat = null, ?float $lng = null, ?string $search = null): array
    {
        $this->ensureConfigured();

        $response = $this->client()->get($this->url('lockers-data'));

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'pudo' => 'PUDO lockers could not be loaded right now.',
            ]);
        }

        $lockers = collect($response->json() ?? [])
            ->map(fn (array $locker): array => $this->normalizeLocker($locker, $lat, $lng))
            ->filter(fn (array $locker): bool => $locker['code'] !== '' && $locker['name'] !== '');

        if ($search) {
            $needle = Str::lower($search);
            $lockers = $lockers->filter(fn (array $locker): bool => str_contains(Str::lower($locker['name'].' '.$locker['address']), $needle));
        }

        if ($lat !== null && $lng !== null) {
            $lockers = $lockers
                ->filter(fn (array $locker): bool => $locker['distance_km'] === null || $locker['distance_km'] <= 50)
                ->sortBy('distance_km');
        }

        return $lockers->values()->take(20)->all();
    }

    public function quote(MallStore $store, array $deliveryData): array
    {
        $this->ensureConfigured();
        $lockerCode = trim((string) ($deliveryData['pudo_locker_code'] ?? ''));

        if ($lockerCode === '') {
            throw ValidationException::withMessages([
                'pudo_locker_code' => 'Select a PUDO locker before checkout.',
            ]);
        }

        $payload = [
            'collection_address' => $this->collectionAddress($store),
            'delivery_address' => [
                'terminal_id' => $lockerCode,
            ],
            'parcels' => [$this->parcelPayload($deliveryData)],
            'opt_in_rates' => [],
            'opt_in_time_based_rates' => [],
            'collection_min_date' => now()->addDay()->toDateString(),
            'delivery_min_date' => now()->addDays(3)->toDateString(),
        ];

        $response = $this->client()->post($this->url('rates'), $payload);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'delivery_method' => 'PUDO could not quote this locker delivery right now.',
            ]);
        }

        $rate = collect($response->json('rates', []))
            ->sortBy(fn (array $rate): float => (float) ($rate['rate'] ?? PHP_FLOAT_MAX))
            ->first();

        if (! $rate) {
            throw ValidationException::withMessages([
                'delivery_method' => 'PUDO returned no available locker delivery rates for this order.',
            ]);
        }

        $deliveryFee = $this->money((float) ($rate['rate'] ?? 0));
        $platformFee = MallMoney::percent($deliveryFee, (string) config('mall.delivery.methods.pudo.platform_fee_percent', '0'));

        return [
            'delivery_fee' => $deliveryFee,
            'platform_fee' => $platformFee,
            'provider_amount' => MallMoney::subtract($deliveryFee, $platformFee),
            'meta' => [
                'pudo_quote' => [
                    'locker_code' => $lockerCode,
                    'locker_name' => $deliveryData['pudo_locker_name'] ?? null,
                    'locker_latitude' => $deliveryData['pudo_locker_latitude'] ?? null,
                    'locker_longitude' => $deliveryData['pudo_locker_longitude'] ?? null,
                    'service_level_code' => data_get($rate, 'service_level.code'),
                    'service_level_name' => data_get($rate, 'service_level.name'),
                    'rate_revision_id' => $rate['rate_revision_id'] ?? null,
                    'charged_weight' => $rate['charged_weight'] ?? null,
                    'rate' => $deliveryFee,
                    'request' => $payload,
                ],
            ],
        ];
    }

    public function createShipmentForPaidOrder(MallOrder $order): void
    {
        $order->loadMissing('store', 'user', 'items', 'fulfillment');
        $fulfillment = $order->fulfillment;

        if (! $fulfillment || $fulfillment->provider !== 'pudo') {
            return;
        }

        if ($fulfillment->external_type === 'pudo_shipment' && $fulfillment->external_id) {
            return;
        }

        try {
            $this->ensureConfigured();
            $payload = $this->shipmentPayload($order, $fulfillment);
            $response = $this->client()->post($this->url('shipments'), $payload);

            if (! $response->successful()) {
                $this->markShipmentFailure($fulfillment, 'PUDO shipment creation failed.', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 1000),
                ]);

                return;
            }

            $shipment = $response->json() ?? [];
            $meta = $fulfillment->meta ?? [];
            $meta['pudo_shipment'] = [
                'id' => $shipment['id'] ?? null,
                'custom_tracking_reference' => $shipment['custom_tracking_reference'] ?? null,
                'status' => $shipment['status'] ?? null,
                'pincode' => $shipment['pincode'] ?? null,
                'service_level_code' => $shipment['service_level_code'] ?? data_get($shipment, 'service_level_obj.code'),
                'service_level_name' => $shipment['service_level_name'] ?? data_get($shipment, 'service_level_obj.name'),
                'response' => $shipment,
            ];

            $fulfillment->update([
                'status' => (string) ($shipment['status'] ?? 'submitted'),
                'external_type' => 'pudo_shipment',
                'external_id' => is_numeric($shipment['id'] ?? null) ? (int) $shipment['id'] : null,
                'meta' => $meta,
            ]);
        } catch (\Throwable $exception) {
            $this->markShipmentFailure($fulfillment, $exception->getMessage());
        }
    }

    public function quotePreview(MallStore $store, array $deliveryData): array
    {
        $quote = $this->quote($store, $deliveryData);

        return [
            'delivery_fee' => $quote['delivery_fee'],
            'label' => $quote['meta']['pudo_quote']['service_level_name'] ?? 'PUDO locker delivery',
            'service_level_code' => $quote['meta']['pudo_quote']['service_level_code'] ?? null,
        ];
    }

    private function shipmentPayload(MallOrder $order, MallFulfillment $fulfillment): array
    {
        $quote = $fulfillment->meta['pudo_quote'] ?? [];
        $request = $quote['request'] ?? [];

        return [
            'collection_address' => $this->collectionAddress($order->store),
            'collection_contact' => $this->storeContact($order),
            'delivery_address' => [
                'terminal_id' => $quote['locker_code'] ?? null,
            ],
            'delivery_contact' => [
                'name' => Str::limit((string) ($order->user?->name ?: 'Mall Customer'), 100, ''),
                'mobile_number' => $fulfillment->contact_phone ?: '0000000000',
                'email' => $order->user?->email ?: config('mail.from.address'),
            ],
            'parcels' => $request['parcels'] ?? [$this->parcelPayload([
                'parcel_weight_kg' => $this->orderWeight($order),
            ])],
            'special_instructions_collection' => 'Life@ Mall order '.$order->order_number,
            'collection_min_date' => now()->addDay()->toISOString(),
            'collection_after' => (string) config('mall.pudo.collection_window_after', '08:00'),
            'collection_before' => (string) config('mall.pudo.collection_window_before', '16:00'),
            'delivery_min_date' => now()->addDays(3)->toISOString(),
            'delivery_after' => (string) config('mall.pudo.delivery_window_after', '10:00'),
            'delivery_before' => (string) config('mall.pudo.delivery_window_before', '17:00'),
            'customer_reference' => $order->order_number,
            'service_level_code' => $quote['service_level_code'] ?? null,
        ];
    }

    private function storeContact(MallOrder $order): array
    {
        $order->store->loadMissing('vendorProfile');

        return [
            'name' => Str::limit((string) ($order->store->name ?: 'Life@ Mall Store'), 100, ''),
            'mobile_number' => $order->store->vendorProfile?->contact_phone ?: '0000000000',
            'email' => $order->store->vendorProfile?->contact_email ?: config('mail.from.address'),
        ];
    }

    private function collectionAddress(MallStore $store): array
    {
        return [
            'type' => 'business',
            'company' => $store->name,
            'street_address' => $store->pickup_address ?: $store->name,
            'entered_address' => $store->pickup_address ?: $store->name,
            'country' => 'South Africa',
            'lat' => $store->pickup_latitude !== null ? (float) $store->pickup_latitude : null,
            'lng' => $store->pickup_longitude !== null ? (float) $store->pickup_longitude : null,
        ];
    }

    private function parcelPayload(array $deliveryData): array
    {
        return [
            'parcel_description' => 'Life@ Mall order',
            'submitted_length_cm' => (float) config('mall.pudo.default_parcel.length_cm', 40),
            'submitted_width_cm' => (float) config('mall.pudo.default_parcel.width_cm', 38),
            'submitted_height_cm' => (float) config('mall.pudo.default_parcel.height_cm', 5),
            'submitted_weight_kg' => max(0.1, (float) ($deliveryData['parcel_weight_kg'] ?? 0.1)),
            'packaging' => (string) config('mall.pudo.default_parcel.packaging', 'Standard flyer'),
        ];
    }

    private function normalizeLocker(array $locker, ?float $lat, ?float $lng): array
    {
        $lockerLat = $this->nullableFloat($locker['latitude'] ?? $locker['lat'] ?? null);
        $lockerLng = $this->nullableFloat($locker['longitude'] ?? $locker['lng'] ?? null);

        return [
            'code' => (string) ($locker['code'] ?? $locker['terminal_id'] ?? ''),
            'name' => (string) ($locker['name'] ?? $locker['title'] ?? ''),
            'address' => (string) ($locker['address'] ?? $locker['location'] ?? ''),
            'latitude' => $lockerLat,
            'longitude' => $lockerLng,
            'distance_km' => $lat !== null && $lng !== null && $lockerLat !== null && $lockerLng !== null
                ? round($this->distanceKm($lat, $lng, $lockerLat, $lockerLng), 2)
                : null,
        ];
    }

    private function client(): PendingRequest
    {
        $key = trim((string) config('mall.pudo.api_key', ''));
        $header = trim((string) config('mall.pudo.auth_header', 'Authorization'));
        $prefix = trim((string) config('mall.pudo.auth_prefix', 'Bearer'));
        $value = $prefix !== '' ? $prefix.' '.$key : $key;

        return Http::acceptJson()
            ->asJson()
            ->timeout((int) config('mall.pudo.timeout', 20))
            ->withHeaders([$header => $value]);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('mall.pudo.base_url', 'https://api-sandbox.pudo.co.za'), '/').'/'.ltrim($path, '/');
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages([
                'delivery_method' => 'PUDO API credentials are not configured yet.',
            ]);
        }
    }

    private function markShipmentFailure(MallFulfillment $fulfillment, string $message, array $context = []): void
    {
        $meta = $fulfillment->meta ?? [];
        $meta['pudo_shipment_error'] = [
            'message' => $message,
            'context' => $context,
            'time' => now()->toISOString(),
        ];

        $fulfillment->update([
            'status' => 'pudo_action_required',
            'meta' => $meta,
        ]);
    }

    private function orderWeight(MallOrder $order): float
    {
        return round((float) $order->items->sum(fn ($item): float => ((float) ($item->parcel_weight_kg ?? 0)) * (int) $item->quantity), 3);
    }

    private function distanceKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadiusKm = 6371;
        $deltaLat = deg2rad($toLat - $fromLat);
        $deltaLng = deg2rad($toLng - $fromLng);
        $lat1 = deg2rad($fromLat);
        $lat2 = deg2rad($toLat);
        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
