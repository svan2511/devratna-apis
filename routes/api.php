<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Api\V1\Admin\OfferController as AdminOfferController;
use App\Http\Controllers\Api\V1\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\MenuController as AdminMenuController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\OfferController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PushTokenController;
use App\Http\Controllers\Api\V1\RazorpayWebhookController;
use App\Http\Controllers\Api\V1\ShopController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Public menu catalogue.
    Route::get('menu', [MenuController::class, 'index'])->middleware('throttle:60,1');
    // Public home banners (admin panel se; empty = app fallback slides).
    Route::get('banners', [BannerController::class, 'index'])->middleware('throttle:60,1');
    // Public live offers (checkout strip + bill preview; billing server-side).
    Route::get('offers', [OfferController::class, 'index'])->middleware('throttle:60,1');
    // Public shop status (open/closed + charges) — app banner ke liye.
    Route::get('shop-status', [ShopController::class, 'status'])->middleware('throttle:60,1');
    // Razorpay server callback — public (HMAC verified), source of truth for payment status.
    Route::post('webhooks/razorpay', [RazorpayWebhookController::class, 'handle'])
        ->middleware('throttle:120,1');
    // Public routes — rate limited against brute force.
    Route::post('auth/request-otp', [AuthController::class, 'requestOtp'])
        ->middleware('throttle:10,1');
    Route::post('auth/verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:15,1');

    // Protected routes — Sanctum Bearer token required.
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        // Prepaid ordering — geofence + min order enforced server-side.
        Route::get('orders', [OrderController::class, 'index'])->middleware('throttle:30,1');
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
        Route::post('orders/verify', [OrderController::class, 'verify'])->middleware('throttle:20,1');
        Route::post('orders/fail', [OrderController::class, 'fail'])->middleware('throttle:20,1');
        // Expo push token (order status notifications).
        Route::post('push-token', [PushTokenController::class, 'store'])->middleware('throttle:20,1');
    });

    // ---------- Admin dashboard (email+password, is_admin only) ----------
    Route::prefix('admin')->group(function (): void {
        Route::post('login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1');

        Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
            Route::get('me', [AdminAuthController::class, 'me']);
            Route::post('logout', [AdminAuthController::class, 'logout']);

            Route::get('dashboard/stats', [AdminDashboardController::class, 'stats']);

            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::get('orders/{id}', [AdminOrderController::class, 'show'])->whereNumber('id');
            Route::patch('orders/{id}/fulfillment', [AdminOrderController::class, 'updateFulfillment'])->whereNumber('id');

            Route::get('menu/categories', [AdminMenuController::class, 'categories']);
            Route::post('menu/categories', [AdminMenuController::class, 'storeCategory']);
            Route::put('menu/categories/{id}', [AdminMenuController::class, 'updateCategory'])->whereNumber('id');
            Route::delete('menu/categories/{id}', [AdminMenuController::class, 'destroyCategory'])->whereNumber('id');

            Route::get('menu/items', [AdminMenuController::class, 'items']);
            Route::post('menu/items', [AdminMenuController::class, 'store']);
            Route::put('menu/items/{id}', [AdminMenuController::class, 'update'])->whereNumber('id');
            Route::patch('menu/items/{id}/availability', [AdminMenuController::class, 'setAvailability'])->whereNumber('id');
            Route::delete('menu/items/{id}', [AdminMenuController::class, 'destroy'])->whereNumber('id');

            Route::get('customers', [AdminCustomerController::class, 'index']);

            Route::get('banners', [AdminBannerController::class, 'index']);
            Route::post('banners', [AdminBannerController::class, 'store']);
            Route::put('banners/{id}', [AdminBannerController::class, 'update'])->whereNumber('id');
            Route::patch('banners/{id}/active', [AdminBannerController::class, 'setActive'])->whereNumber('id');
            Route::delete('banners/{id}', [AdminBannerController::class, 'destroy'])->whereNumber('id');

            Route::get('offers', [AdminOfferController::class, 'index']);
            Route::post('offers', [AdminOfferController::class, 'store']);
            Route::put('offers/{id}', [AdminOfferController::class, 'update'])->whereNumber('id');
            Route::patch('offers/{id}/active', [AdminOfferController::class, 'setActive'])->whereNumber('id');
            Route::delete('offers/{id}', [AdminOfferController::class, 'destroy'])->whereNumber('id');

            Route::get('settings', [AdminSettingsController::class, 'show']);
            Route::put('settings', [AdminSettingsController::class, 'update']);
        });
    });
});
