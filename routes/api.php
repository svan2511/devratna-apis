<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Public menu catalogue.
    Route::get('menu', [MenuController::class, 'index'])->middleware('throttle:60,1');
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
        Route::post('auth/logout', [AuthController::class, 'logout']);
        // Prepaid ordering — geofence + min order enforced server-side.
        Route::get('orders', [OrderController::class, 'index'])->middleware('throttle:30,1');
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
        Route::post('orders/verify', [OrderController::class, 'verify'])->middleware('throttle:20,1');
        Route::post('orders/fail', [OrderController::class, 'fail'])->middleware('throttle:20,1');
    });
});
