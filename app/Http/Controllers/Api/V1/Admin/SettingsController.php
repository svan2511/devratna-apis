<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\ShopSettings;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shop settings — geofence + pricing.
 * Admin override turant lagu hota hai (cache-backed, single source of truth).
 */
class SettingsController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        return $this->success(['settings' => ShopSettings::all()], 'Settings loaded.');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'min_order' => 'required|integer|min:0|max:10000',
            'delivery_charge' => 'required|integer|min:0|max:1000',
            'radius_m' => 'required|integer|min:100|max:20000',
            'shop_open' => 'required|boolean',
            'delivery_mode' => 'nullable|string|in:fixed,distance',
            'delivery_base' => 'nullable|integer|min:0|max:1000',
            'delivery_free_m' => 'nullable|integer|min:0|max:20000',
            'delivery_per_500m' => 'nullable|integer|min:0|max:500',
        ]);

        $settings = ShopSettings::save($data);

        return $this->success(['settings' => array_merge($settings, [
            'note' => 'Sab settings turant lagu — app, website aur kitchen guard yahi padhte hain.',
        ])], 'Settings saved.');
    }
}
