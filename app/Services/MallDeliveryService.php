<?php

namespace App\Services;

use App\Models\MallFulfillment;
use App\Models\MallOrder;
use App\Support\Mall\MallMoney;
use Illuminate\Validation\ValidationException;

class MallDeliveryService
{
    public function __construct(
        private readonly MallTransportDeliveryService $transportDeliveryService,
        private readonly MallPudoService $pudoService
    ) {}

    public function areas(): array
    {
        return [
            'local' => 'Local delivery area',
            'non_local' => 'Outside local area',
        ];
    }

    public function optionsForArea(?string $area, array $deliveryData = []): array
    {
        $area = $area ?: (string) config('mall.delivery.default_area', 'local');

        return collect(config('mall.delivery.methods', []))
            ->filter(fn (array $option) => ($option['area'] ?? 'all') === 'all' || ($option['area'] ?? null) === $area)
            ->map(fn (array $option, string $provider) => $this->displayOption($provider, $area, $deliveryData))
            ->all();
    }

    public function availableProvidersForArea(?string $area): array
    {
        $area = $area ?: (string) config('mall.delivery.default_area', 'local');

        return collect(config('mall.delivery.methods', []))
            ->filter(fn (array $option) => ($option['area'] ?? 'all') === 'all' || ($option['area'] ?? null) === $area)
            ->keys()
            ->all();
    }

    public function quote(string $provider, string $area, array $deliveryData = []): array
    {
        $method = config('mall.delivery.methods.'.$provider);

        if (! is_array($method) || (($method['area'] ?? 'all') !== 'all' && ($method['area'] ?? null) !== $area)) {
            throw ValidationException::withMessages([
                'delivery_method' => 'That delivery method is not available for this order.',
            ]);
        }

        if ($provider === 'taxi') {
            $transportQuote = $this->transportDeliveryService->quote($deliveryData);

            return [
                'provider' => $provider,
                'label' => (string) ($method['label'] ?? ucfirst($provider)),
                'description' => (string) ($method['description'] ?? ''),
                'delivery_area' => $area,
                'delivery_fee' => $transportQuote['delivery_fee'],
                'platform_fee' => $transportQuote['platform_fee'],
                'provider_amount' => $transportQuote['provider_amount'],
                'meta' => $transportQuote['meta'],
            ];
        }

        if ($provider === 'pudo') {
            $pudoQuote = $this->pudoService->quote($deliveryData['store'], $deliveryData);

            return [
                'provider' => $provider,
                'label' => (string) ($method['label'] ?? 'PUDO'),
                'description' => (string) ($method['description'] ?? ''),
                'delivery_area' => $area,
                'delivery_fee' => $pudoQuote['delivery_fee'],
                'platform_fee' => $pudoQuote['platform_fee'],
                'provider_amount' => $pudoQuote['provider_amount'],
                'meta' => $pudoQuote['meta'],
            ];
        }

        $deliveryFee = (string) ($method['fee'] ?? '0.00');
        $platformFee = MallMoney::percent($deliveryFee, (string) ($method['platform_fee_percent'] ?? '0'));

        return [
            'provider' => $provider,
            'label' => (string) ($method['label'] ?? ucfirst($provider)),
            'description' => (string) ($method['description'] ?? ''),
            'delivery_area' => $area,
            'delivery_fee' => $deliveryFee,
            'platform_fee' => $platformFee,
            'provider_amount' => MallMoney::subtract($deliveryFee, $platformFee),
            'meta' => [],
        ];
    }

    public function attachFulfillment(MallOrder $order, array $quote, array $deliveryData): MallFulfillment
    {
        return $order->fulfillment()->create([
            'provider' => $quote['provider'],
            'label' => $quote['label'],
            'status' => $quote['provider'] === 'pickup' ? 'ready_for_pickup' : 'pending',
            'delivery_fee' => $quote['delivery_fee'],
            'platform_fee' => $quote['platform_fee'],
            'provider_amount' => $quote['provider_amount'],
            'delivery_area' => $quote['delivery_area'],
            'delivery_address' => $deliveryData['delivery_address'] ?? null,
            'contact_phone' => $deliveryData['contact_phone'] ?? null,
            'meta' => [
                'description' => $quote['description'],
                'adapter' => $quote['provider'] === 'taxi' ? 'transport_delivery_pending' : null,
                'pickup_address' => $deliveryData['pickup_address'] ?? null,
                'pickup_latitude' => $deliveryData['pickup_latitude'] ?? null,
                'pickup_longitude' => $deliveryData['pickup_longitude'] ?? null,
                'delivery_latitude' => $deliveryData['delivery_latitude'] ?? null,
                'delivery_longitude' => $deliveryData['delivery_longitude'] ?? null,
                'pudo_locker_code' => $deliveryData['pudo_locker_code'] ?? null,
                'pudo_locker_name' => $deliveryData['pudo_locker_name'] ?? null,
                'pudo_locker_latitude' => $deliveryData['pudo_locker_latitude'] ?? null,
                'pudo_locker_longitude' => $deliveryData['pudo_locker_longitude'] ?? null,
            ] + ($quote['meta'] ?? []),
        ]);
    }

    private function displayOption(string $provider, string $area, array $deliveryData): array
    {
        if ($provider === 'taxi' && empty($deliveryData['delivery_distance_km'])) {
            $method = config('mall.delivery.methods.'.$provider, []);

            return [
                'provider' => $provider,
                'label' => (string) ($method['label'] ?? ucfirst($provider)),
                'description' => (string) ($method['description'] ?? ''),
                'delivery_area' => $area,
                'delivery_fee' => null,
                'platform_fee' => null,
                'provider_amount' => null,
                'meta' => [],
            ];
        }

        if ($provider === 'pudo' && empty($deliveryData['pudo_locker_code'])) {
            $method = config('mall.delivery.methods.'.$provider, []);

            return [
                'provider' => $provider,
                'label' => (string) ($method['label'] ?? 'PUDO'),
                'description' => (string) ($method['description'] ?? ''),
                'delivery_area' => $area,
                'delivery_fee' => null,
                'platform_fee' => null,
                'provider_amount' => null,
                'meta' => [],
            ];
        }

        return $this->quote($provider, $area, $deliveryData);
    }
}
