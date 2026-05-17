<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TransportVehicle;

class TransportFareService
{
    /**
     * @return array{quoted_amount: float, platform_fee: float, driver_amount: float}
     */
    public function estimate(TransportVehicle $vehicle, float $distanceKm, int $passengerCount = 0): array
    {
        $distance = max(0, $distanceKm);
        $people = max(0, $passengerCount);

        $amount = (float) $vehicle->base_fee + ($distance * (float) $vehicle->per_km_fee);

        if ($vehicle->pricing_mode === 'per_km_plus_people') {
            $amount += $people * (float) $vehicle->per_person_fee;
        }

        $amount = max($amount, (float) $vehicle->minimum_fee);
        $quoted = round($amount, 2);
        $platformFee = round($quoted * $this->platformCommissionRate(), 2);

        return [
            'quoted_amount' => $quoted,
            'platform_fee' => $platformFee,
            'driver_amount' => round($quoted - $platformFee, 2),
        ];
    }

    private function platformCommissionRate(): float
    {
        return max(0, (float) Setting::getValue('transport.platform_fee_percent', 10)) / 100;
    }
}
