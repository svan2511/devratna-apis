<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Public shop status — app banner + checkout guard ke liye.
 * Admin dashboard ke Settings > shop_open se control hota hai.
 */
class ShopController extends Controller
{
    use ApiResponse;

    public function status(): JsonResponse
    {
        return $this->success([
            'shop_open' => (bool) cache('shop_open', true),
            'min_order' => (int) config('services.shop.min_order', 500),
            'delivery_charge' => (int) config('services.shop.delivery_charge', 40),
            'radius_m' => (int) config('services.shop.radius_m', 1000),
        ], 'Shop status.');
    }
}
