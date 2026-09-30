<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ShopSettings;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Public shop status — app banner + checkout guard + live pricing ke liye.
 * Admin dashboard ke Settings se control hota hai (turant effective).
 */
class ShopController extends Controller
{
    use ApiResponse;

    public function status(): JsonResponse
    {
        return $this->success(ShopSettings::all(), 'Shop status.');
    }
}
