<?php

namespace App\Services\Geo;

class HaversineDistance
{
    /**
     * Distance in meters between two WGS84 points.
     */
    public function meters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    public function riskLevel(?float $distanceMeters): ?string
    {
        if ($distanceMeters === null) {
            return null;
        }

        $warning = (float) config('collection.gps.warning_meters', 200);
        $high = (float) config('collection.gps.high_risk_meters', 1000);

        if ($distanceMeters >= $high) {
            return 'high_risk';
        }

        if ($distanceMeters >= $warning) {
            return 'warning';
        }

        return 'ok';
    }
}
