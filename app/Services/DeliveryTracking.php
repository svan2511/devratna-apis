<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderLocation;

/**
 * Small, deterministic delivery maths shared by customer tracking and tests.
 */
final class DeliveryTracking
{
    private const AVG_SPEED_METRES_PER_MINUTE = 300;

    private const ARRIVAL_RADIUS_METRES = 40;

    public static function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }

    public static function etaMinutes(?OrderLocation $location, Order $order): ?int
    {
        if ($location === null || $order->customer_lat === null || $order->customer_lng === null) {
            return null;
        }

        $distance = self::distanceMetres(
            (float) $location->latitude,
            (float) $location->longitude,
            (float) $order->customer_lat,
            (float) $order->customer_lng,
        );

        if ($distance <= 25) {
            return 0;
        }

        return max(1, (int) ceil($distance / self::AVG_SPEED_METRES_PER_MINUTE));
    }

    /**
     * Rider destination ke radius me pahuncha to arrived_at set karo.
     * Atomic update ki wajah se arrival push sirf ek baar jati hai.
     */
    public static function markArrivedIfNear(Order $order, float $lat, float $lng): bool
    {
        if ($order->arrived_at !== null || $order->delivered_at !== null) {
            return false;
        }

        if ($order->customer_lat === null || $order->customer_lng === null) {
            return false;
        }

        $distance = self::distanceMetres(
            $lat,
            $lng,
            (float) $order->customer_lat,
            (float) $order->customer_lng,
        );

        if ($distance > self::ARRIVAL_RADIUS_METRES) {
            return false;
        }

        $updated = Order::query()
            ->whereKey($order->getKey())
            ->whereNull('arrived_at')
            ->whereNull('delivered_at')
            ->update(['arrived_at' => now()]);

        if ($updated === 0) {
            return false;
        }

        $order->refresh();

        return true;
    }

    /**
     * @return array{lat: float, lng: float, accuracy_m: int|null, recorded_at: string|null, age_seconds: int|null, is_stale: bool}|null
     */
    public static function locationPayload(?OrderLocation $location): ?array
    {
        if ($location === null) {
            return null;
        }

        $age = $location->recorded_at?->diffInSeconds(now());

        return [
            'lat' => (float) $location->latitude,
            'lng' => (float) $location->longitude,
            'accuracy_m' => $location->accuracy_m !== null ? (int) $location->accuracy_m : null,
            'recorded_at' => $location->recorded_at?->toIso8601String(),
            'age_seconds' => $age !== null ? (int) $age : null,
            'is_stale' => $age !== null && $age > 90,
        ];
    }
}
