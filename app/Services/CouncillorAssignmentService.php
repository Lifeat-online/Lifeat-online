<?php

namespace App\Services;

use App\Models\Councillor;

class CouncillorAssignmentService
{
    public function assign(float $latitude, float $longitude, string $category): ?Councillor
    {
        $councillors = Councillor::query()
            ->where('is_active', true)
            ->with(['areas' => fn ($q) => $q->where('is_active', true)])
            ->get();

        foreach ($councillors as $councillor) {
            $responsibilities = $councillor->category_responsibilities ?? [];
            if ($responsibilities && ! in_array($category, $responsibilities, true)) {
                continue;
            }

            foreach ($councillor->areas as $area) {
                if ($this->containsPoint($area->geojson, $latitude, $longitude)) {
                    return $councillor;
                }
            }
        }

        return null;
    }

    private function containsPoint(array $geojson, float $latitude, float $longitude): bool
    {
        $type = $geojson['type'] ?? null;
        $coordinates = $geojson['coordinates'] ?? null;

        if (! $type || ! is_array($coordinates)) {
            return false;
        }

        if ($type === 'Polygon') {
            return $this->polygonContainsPoint($coordinates, $latitude, $longitude);
        }

        if ($type === 'MultiPolygon') {
            foreach ($coordinates as $polygonCoordinates) {
                if ($this->polygonContainsPoint($polygonCoordinates, $latitude, $longitude)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function polygonContainsPoint(array $polygonCoordinates, float $latitude, float $longitude): bool
    {
        $outerRing = $polygonCoordinates[0] ?? null;
        if (! is_array($outerRing) || count($outerRing) < 4) {
            return false;
        }

        $inside = false;
        $x = $longitude;
        $y = $latitude;

        $count = count($outerRing);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) ($outerRing[$i][0] ?? 0.0);
            $yi = (float) ($outerRing[$i][1] ?? 0.0);
            $xj = (float) ($outerRing[$j][0] ?? 0.0);
            $yj = (float) ($outerRing[$j][1] ?? 0.0);

            $intersects = (($yi > $y) !== ($yj > $y)) && ($x < (($xj - $xi) * ($y - $yi)) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}

