<?php

declare(strict_types=1);

namespace App\Services\SelfService;

use App\Enums\WorkMode;
use App\Models\Employee;
use App\Services\Settings\TenantSettings;

/** Spec §6.6: is a GPS fix inside the employee's branch geofence? Field staff are never fenced. */
final class GeoFence
{
    public function __construct(private readonly TenantSettings $settings) {}

    /** @return array{inside: bool, distance_m: ?int, radius_m: ?int, reason: string} */
    public function check(Employee $employee, float $lat, float $lng, ?int $accuracyM): array
    {
        if ($employee->work_mode === WorkMode::Field) {
            return ['inside' => true, 'distance_m' => null, 'radius_m' => null, 'reason' => 'field_staff'];
        }
        $branch = $employee->branch;
        if ($branch === null || $branch->lat === null || $branch->lng === null) {
            return ['inside' => false, 'distance_m' => null, 'radius_m' => null, 'reason' => 'branch_has_no_location'];
        }
        $radius = (int) ($branch->geofence_radius_m ?: $this->settings->get('geofence_default_radius_m', 150));
        $distance = (int) round(self::metres($lat, $lng, (float) $branch->lat, (float) $branch->lng));
        // A poor fix that could still be inside gets the benefit of the doubt only up to 100 m of slack.
        $slack = min(100, max(0, (int) $accuracyM));

        return ['inside' => $distance <= $radius + $slack, 'distance_m' => $distance, 'radius_m' => $radius, 'reason' => 'distance'];
    }

    /** Haversine distance in metres. */
    public static function metres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6_371_000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}
