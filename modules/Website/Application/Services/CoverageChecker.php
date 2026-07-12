<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Website\Application\Services;

use WorkEddy\Modules\Website\Settings\WebsiteSettings;

final class CoverageChecker
{
    public function __construct(
        private readonly WebsiteSettings $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function assess(?string $address, ?float $latitude, ?float $longitude): array
    {
        $address = trim((string) $address);
        $pops = $this->settings->coveragePopLocations();

        if ($address === '' && ($latitude === null || $longitude === null)) {
            throw new \InvalidArgumentException('Provide an address or coordinates to check coverage.');
        }

        $nearest = null;
        $nearestDistance = null;

        if ($latitude !== null && $longitude !== null) {
            foreach ($pops as $pop) {
                $distance = $this->distanceKm(
                    $latitude,
                    $longitude,
                    (float) $pop['latitude'],
                    (float) $pop['longitude'],
                );

                if ($nearestDistance === null || $distance < $nearestDistance) {
                    $nearestDistance = $distance;
                    $nearest = $pop;
                }
            }
        } elseif ($address !== '') {
            $matched = $this->matchAddressToPop($address, $pops);
            if ($matched !== null) {
                $nearest = $matched;
                $nearestDistance = 0.0;
            }
        }

        if ($nearest === null || $nearestDistance === null) {
            return [
                'status' => 'ok',
                'data' => [
                    'covered' => false,
                    'serviceability' => 'unavailable',
                    'message' => 'We could not match that address to a live service zone. Share precise coordinates or use current location.',
                    'location' => $address,
                ],
            ];
        }

        $directRadius = $this->settings->directCoverageRadiusKm();
        $extendedRadius = $this->settings->extendedCoverageRadiusKm();

        if ($nearestDistance <= $directRadius) {
            return [
                'status' => 'ok',
                'data' => [
                    'covered' => true,
                    'serviceability' => 'available',
                    'message' => 'Service is available in your area.',
                    'latency' => $nearest['latency'] ?? '< 10ms',
                    'fiber_distance_km' => round(max($nearestDistance, 0.3), 1),
                    'max_speed' => $nearest['max_speed'] ?? '10Gbps',
                    'plans' => $this->settings->coverageAvailablePlans(),
                    'nearest_pop' => $nearest['name'] ?? null,
                    'location' => $address,
                ],
            ];
        }

        if ($nearestDistance <= $extendedRadius) {
            return [
                'status' => 'ok',
                'data' => [
                    'covered' => false,
                    'serviceability' => 'extended',
                    'message' => 'You are outside the immediate fiber footprint, but we can serve this location through engineering review.',
                    'latency' => $nearest['extended_latency'] ?? '15-25ms',
                    'fiber_distance_km' => round($nearestDistance, 1),
                    'max_speed' => $nearest['extended_speed'] ?? '500Mbps',
                    'plans' => $this->settings->coverageAvailablePlans(),
                    'nearest_pop' => $nearest['name'] ?? null,
                    'location' => $address,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'data' => [
                'covered' => false,
                'serviceability' => 'unavailable',
                'message' => 'This location is outside our current service footprint.',
                'fiber_distance_km' => round($nearestDistance, 1),
                'nearest_pop' => $nearest['name'] ?? null,
                'location' => $address,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $pops
     * @return array<string, mixed>|null
     */
    private function matchAddressToPop(string $address, array $pops): ?array
    {
        $normalizedAddress = strtolower($address);

        foreach ($pops as $pop) {
            $aliases = $pop['aliases'] ?? [];
            if (!is_array($aliases)) {
                continue;
            }

            foreach ($aliases as $alias) {
                if (is_string($alias) && $alias !== '' && str_contains($normalizedAddress, strtolower($alias))) {
                    return $pop;
                }
            }
        }

        return null;
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return 2 * $earthRadius * asin(min(1.0, sqrt($a)));
    }
}
