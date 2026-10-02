<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Delivery charge calculator — billing ka single source of truth.
 * - fixed: hamesha flat charge.
 * - distance: FREE_UPTO_M tak base, uske baad har 500m (ya hisse) pe extra.
 * Mobile app me same formula ka mirror hai (preview ke liye).
 */
final class DeliveryCharge
{
    /**
     * @param  array{delivery_mode?: string, delivery_charge?: int, delivery_base?: int, delivery_free_m?: int, delivery_per_500m?: int}  $shop
     */
    public static function for(int $distanceM, array $shop): int
    {
        if (($shop['delivery_mode'] ?? 'fixed') !== 'distance') {
            return max(0, (int) ($shop['delivery_charge'] ?? 0));
        }

        $base = max(0, (int) ($shop['delivery_base'] ?? 0));
        $freeUpTo = max(0, (int) ($shop['delivery_free_m'] ?? 1000));
        $perSlab = max(0, (int) ($shop['delivery_per_500m'] ?? 0));

        $extra = $distanceM - $freeUpTo;
        if ($extra <= 0 || $perSlab <= 0) {
            return $base;
        }

        return $base + $perSlab * (int) ceil($extra / 500);
    }
}
