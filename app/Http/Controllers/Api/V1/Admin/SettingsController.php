<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shop settings — geofence + pricing.
 * Values .env se aate hain; admin override cache me rakha jata hai.
 * NOTE: Full DB-backed settings Phase-2 me — abhi read + validate + response.
 */
class SettingsController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        return $this->success(['settings' => [
            'min_order' => (int) config('services.shop.min_order', 500),
            'delivery_charge' => (int) config('services.shop.delivery_charge', 40),
            'radius_m' => (int) config('services.shop.radius_m', 1000),
            'shop_open' => (bool) cache('shop_open', true),
        ]], 'Settings loaded.');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'min_order' => 'required|integer|min:0|max:10000',
            'delivery_charge' => 'required|integer|min:0|max:1000',
            'radius_m' => 'required|integer|min:100|max:20000',
            'shop_open' => 'required|boolean',
        ]);

        // shop_open turant effective; pricing/geofence ke liye .env hi source of truth
        // (Render restart ke bina env change nahi hota) — isliye admin ko clear message.
        cache(['shop_open' => (bool) $data['shop_open']], now()->addDays(30));

        return $this->success(['settings' => [
            'min_order' => (int) $data['min_order'],
            'delivery_charge' => (int) $data['delivery_charge'],
            'radius_m' => (int) $data['radius_m'],
            'shop_open' => (bool) $data['shop_open'],
            'note' => 'shop_open turant lagu. min_order / delivery / radius ke liye .env update + deploy chahiye (Phase-2 me DB-backed karenge).',
        ]], 'Settings saved.');
    }
}
