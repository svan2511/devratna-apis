<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Shop settings — geofence + pricing + open switch.
 *
 * .env values defaults hain; admin dashboard override cache me rakhta hai
 * taaki bina redeploy ke turant lagu ho (mobile app + webapp + kitchen
 * guard sab yahi se padhte hain — single source of truth).
 */
final class ShopSettings
{
    /**
     * @return array{min_order: int, delivery_charge: int, radius_m: int, shop_open: bool}
     */
    public static function all(): array
    {
        return [
            'min_order' => (int) cache('shop_min_order', config('services.shop.min_order', 500)),
            'delivery_charge' => (int) cache('shop_delivery_charge', config('services.shop.delivery_charge', 40)),
            'radius_m' => (int) cache('shop_radius_m', config('services.shop.radius_m', 1000)),
            'shop_open' => (bool) cache('shop_open', true),
        ];
    }

    /**
     * @param  array{min_order: int, delivery_charge: int, radius_m: int, shop_open: bool}  $data
     * @return array{min_order: int, delivery_charge: int, radius_m: int, shop_open: bool}
     */
    public static function save(array $data): array
    {
        // NOTE: cache([...]) helper sirf FIRST key likhta hai (Laravel 13) —
        // isliye explicit putMany.
        cache()->putMany([
            'shop_min_order' => (int) $data['min_order'],
            'shop_delivery_charge' => (int) $data['delivery_charge'],
            'shop_radius_m' => (int) $data['radius_m'],
            'shop_open' => (bool) $data['shop_open'],
        ], now()->addDays(30));

        return self::all();
    }
}
