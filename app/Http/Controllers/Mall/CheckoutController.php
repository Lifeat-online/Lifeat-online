<?php

namespace App\Http\Controllers\Mall;

use App\Http\Controllers\Controller;
use App\Models\MallCart;
use App\Models\MallOrder;
use App\Models\MallPayment;
use App\Models\MallStore;
use App\Services\MallCartService;
use App\Services\MallDeliveryService;
use App\Services\MallPayFastService;
use App\Services\MallPudoService;
use App\Services\MallTransportDeliveryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private MallCartService $cartService,
        private MallPayFastService $payFastService,
        private MallDeliveryService $deliveryService,
        private MallTransportDeliveryService $transportDeliveryService,
        private MallPudoService $pudoService
    ) {}

    public function show(MallStore $store): View|RedirectResponse
    {
        abort_unless($store->status === 'active', 404);

        $cart = $this->cartService->getCart($store);

        if ($cart->isEmpty()) {
            return redirect()
                ->route('mall.stores.index', $store)
                ->with('info', 'Your basket is empty.');
        }

        $cart->loadMissing('items.product');

        $parcelWeightKg = $this->cartParcelWeightKg($cart);
        $taxiPricingVehicles = $this->transportDeliveryService->pricingVehicles();

        return view('mall.checkout', [
            'store' => $store,
            'cart' => $cart,
            'parcelWeightKg' => $parcelWeightKg,
            'missingParcelWeightProducts' => $this->productsMissingParcelWeight($cart),
            'activeTaxiVehicleTypes' => $this->activeTaxiVehicleTypesForWeight($taxiPricingVehicles, $parcelWeightKg),
            'deliveryAreas' => $this->deliveryService->areas(),
            'deliveryOptionsByArea' => collect($this->deliveryService->areas())
                ->mapWithKeys(fn (string $label, string $area) => [$area => $this->deliveryService->optionsForArea($area)])
                ->all(),
            'defaultDeliveryArea' => config('mall.delivery.default_area', 'local'),
            'taxiPricingVehicles' => $taxiPricingVehicles,
        ]);
    }

    public function initiate(MallStore $store, Request $request): View|RedirectResponse
    {
        abort_unless($store->status === 'active', 404);

        $request->merge([
            'delivery_area' => $request->input('delivery_area', config('mall.delivery.default_area', 'local')),
            'delivery_method' => $request->input('delivery_method', config('mall.delivery.default_method', 'pickup')),
        ]);

        $deliveryArea = (string) $request->input('delivery_area');
        $availableDeliveryMethods = $this->deliveryService->availableProvidersForArea($deliveryArea);
        $deliveryMethod = (string) $request->input('delivery_method');

        $validated = $request->validate([
            'delivery_area' => ['required', Rule::in(array_keys($this->deliveryService->areas()))],
            'delivery_method' => ['required', Rule::in($availableDeliveryMethods)],
            'delivery_address' => [Rule::requiredIf($deliveryMethod !== 'pickup'), 'nullable', 'string', 'max:500'],
            'delivery_latitude' => [Rule::requiredIf($deliveryMethod === 'taxi'), 'nullable', 'numeric', 'between:-35,-22'],
            'delivery_longitude' => [Rule::requiredIf($deliveryMethod === 'taxi'), 'nullable', 'numeric', 'between:16,33'],
            'contact_phone' => [Rule::requiredIf($deliveryMethod !== 'pickup'), 'nullable', 'string', 'max:30'],
            'pudo_locker_code' => [Rule::requiredIf($deliveryMethod === 'pudo'), 'nullable', 'string', 'max:30'],
            'pudo_locker_name' => ['nullable', 'string', 'max:200'],
            'pudo_locker_latitude' => ['nullable', 'numeric', 'between:-35,-22'],
            'pudo_locker_longitude' => ['nullable', 'numeric', 'between:16,33'],
            'required_vehicle_type' => ['nullable', Rule::in($this->transportDeliveryService->pricingVehicleTypes())],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $cart = $this->cartService->getCart($store);
        $cart->loadMissing('items.product');

        if ($cart->isEmpty()) {
            return redirect()
                ->route('mall.stores.index', $store)
                ->with('error', 'Your basket is empty.');
        }

        foreach ($cart->items as $item) {
            if ($item->product?->manage_stock && $item->product->stock_qty < $item->quantity) {
                return back()->withErrors([
                    'stock' => $item->product->name.' is out of stock.',
                ]);
            }
        }

        if ($deliveryMethod === 'taxi') {
            $missingParcelWeightProducts = $this->productsMissingParcelWeight($cart);

            if ($missingParcelWeightProducts !== []) {
                throw ValidationException::withMessages([
                    'delivery_method' => 'Taxi delivery needs vendor parcel kg estimates for: '.implode(', ', $missingParcelWeightProducts).'.',
                ]);
            }

            $validated['parcel_weight_kg'] = $this->cartParcelWeightKg($cart);
            $validated = $this->withTaxiDeliveryDistance($store, $validated);
        }

        if ($deliveryMethod === 'pudo') {
            $missingParcelWeightProducts = $this->productsMissingParcelWeight($cart);

            if ($missingParcelWeightProducts !== []) {
                throw ValidationException::withMessages([
                    'delivery_method' => 'PUDO needs vendor parcel kg estimates for: '.implode(', ', $missingParcelWeightProducts).'.',
                ]);
            }

            $validated = $this->withPudoDeliveryData($store, $cart, $validated);
        }

        $deliveryQuote = $this->deliveryService->quote($validated['delivery_method'], $validated['delivery_area'], $validated);

        $order = DB::transaction(function () use ($cart, $request, $validated, $deliveryQuote): MallOrder {
            $order = MallOrder::createFromCart($cart, $request->user(), $validated['notes'] ?? null, $deliveryQuote);
            $this->deliveryService->attachFulfillment($order, $deliveryQuote, $validated);

            MallPayment::create([
                'mall_order_id' => $order->id,
                'm_payment_id' => $order->order_number,
                'amount' => $order->total,
                'status' => 'initiated',
            ]);

            return $order->load('store', 'user');
        });

        $paymentData = $this->payFastService->buildPaymentData($order);

        return view('mall.payfast_redirect', [
            'paymentUrl' => $paymentData['url'],
            'paymentFields' => $paymentData['fields'],
        ]);
    }

    public function pudoLockers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-35,-22'],
            'lng' => ['nullable', 'numeric', 'between:16,33'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $this->pudoService->configured()) {
            return response()->json([
                'ok' => false,
                'configured' => false,
                'message' => 'PUDO API credentials are not configured yet.',
                'lockers' => [],
            ], 503);
        }

        return response()->json([
            'ok' => true,
            'configured' => true,
            'lockers' => $this->pudoService->lockers(
                isset($validated['lat']) ? (float) $validated['lat'] : null,
                isset($validated['lng']) ? (float) $validated['lng'] : null,
                $validated['q'] ?? null
            ),
        ]);
    }

    public function pudoQuote(MallStore $store, Request $request): JsonResponse
    {
        abort_unless($store->status === 'active', 404);

        $validated = $request->validate([
            'pudo_locker_code' => ['required', 'string', 'max:30'],
            'pudo_locker_name' => ['nullable', 'string', 'max:200'],
            'pudo_locker_latitude' => ['nullable', 'numeric', 'between:-35,-22'],
            'pudo_locker_longitude' => ['nullable', 'numeric', 'between:16,33'],
        ]);

        $cart = $this->cartService->getCart($store);
        $cart->loadMissing('items.product');

        if ($cart->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'Your basket is empty.',
            ], 422);
        }

        $quote = $this->pudoService->quotePreview($store, $this->withPudoDeliveryData($store, $cart, $validated));

        return response()->json([
            'ok' => true,
            'quote' => $quote,
        ]);
    }

    public function return(MallStore $store): View
    {
        return view('mall.checkout_return', [
            'store' => $store,
            'message' => 'Thank you. PayFast is confirming your payment and we will update your mall order shortly.',
        ]);
    }

    public function cancel(MallStore $store): RedirectResponse
    {
        return redirect()
            ->route('mall.checkout.show', $store)
            ->with('info', 'Payment was cancelled. Your basket is still saved at '.$store->name.'.');
    }

    private function withTaxiDeliveryDistance(MallStore $store, array $validated): array
    {
        if ($store->pickup_latitude === null || $store->pickup_longitude === null) {
            throw ValidationException::withMessages([
                'delivery_method' => 'This store needs a pickup point before taxi delivery can be quoted.',
            ]);
        }

        $distance = $this->distanceKm(
            (float) $store->pickup_latitude,
            (float) $store->pickup_longitude,
            (float) $validated['delivery_latitude'],
            (float) $validated['delivery_longitude'],
        );

        return $validated + [
            'pickup_address' => $store->pickup_address ?: 'Mall pickup: '.$store->name,
            'pickup_latitude' => (float) $store->pickup_latitude,
            'pickup_longitude' => (float) $store->pickup_longitude,
            'delivery_distance_km' => max(0.1, round($distance, 1)),
        ];
    }

    private function withPudoDeliveryData(MallStore $store, MallCart $cart, array $validated): array
    {
        if ($store->pickup_latitude === null || $store->pickup_longitude === null || blank($store->pickup_address)) {
            throw ValidationException::withMessages([
                'delivery_method' => 'This store needs a pickup point before PUDO can be quoted.',
            ]);
        }

        return $validated + [
            'store' => $store,
            'pickup_address' => $store->pickup_address,
            'pickup_latitude' => (float) $store->pickup_latitude,
            'pickup_longitude' => (float) $store->pickup_longitude,
            'parcel_weight_kg' => $this->cartParcelWeightKg($cart),
        ];
    }

    private function distanceKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadiusKm = 6371;
        $deltaLat = deg2rad($toLat - $fromLat);
        $deltaLng = deg2rad($toLng - $fromLng);
        $lat1 = deg2rad($fromLat);
        $lat2 = deg2rad($toLat);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function cartParcelWeightKg(MallCart $cart): float
    {
        $cart->loadMissing('items.product');

        return round((float) $cart->items->sum(function ($item): float {
            return ((float) ($item->product?->parcel_weight_kg ?? 0)) * (int) $item->quantity;
        }), 3);
    }

    private function productsMissingParcelWeight(MallCart $cart): array
    {
        $cart->loadMissing('items.product');

        return $cart->items
            ->filter(fn ($item): bool => $item->product !== null && (float) ($item->product->parcel_weight_kg ?? 0) <= 0)
            ->map(fn ($item): string => (string) $item->product->name)
            ->unique()
            ->values()
            ->all();
    }

    private function activeTaxiVehicleTypesForWeight(array $pricingVehicles, float $parcelWeightKg): array
    {
        return collect($pricingVehicles)
            ->filter(function (array $vehicle) use ($parcelWeightKg): bool {
                if ($parcelWeightKg <= 0) {
                    return true;
                }

                return $vehicle['max_weight_kg'] === null || (float) $vehicle['max_weight_kg'] >= $parcelWeightKg;
            })
            ->pluck('vehicle_type')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
