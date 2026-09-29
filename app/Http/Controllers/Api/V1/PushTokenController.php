<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User ke phone ka Expo push token save karo (order status pushes ke liye).
 * Token user ke account se juda hai — logout/login pe dobara register hota hai.
 */
class PushTokenController extends Controller
{
    use ApiResponse;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|min:10|max:255',
            'platform' => 'nullable|in:android,ios,web',
        ]);

        $user = $request->user();

        PushToken::query()->updateOrCreate(
            ['token' => trim($data['token'])],
            ['user_id' => $user->id, 'platform' => $data['platform'] ?? null],
        );

        return $this->success(null, 'Push token saved.');
    }
}
