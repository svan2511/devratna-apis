<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Admin email+password login (dashboard ke liye).
 * Customer OTP flow se alag — sirf is_admin users.
 */
class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:1|max:255',
        ]);

        try {
            $user = \App\Models\User::query()->where('email', $data['email'])->first();

            if ($user === null || $user->password === null || ! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['email' => ['Invalid email or password.']]);
            }

            if (! $user->isAdmin()) {
                return $this->failure('Forbidden. Admin access required.', 403);
            }

            $user->tokens()->where('name', 'admin')->delete();
            $token = $user->createToken('admin')->plainTextToken;

            return $this->success([
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'token' => $token,
                'token_type' => 'Bearer',
            ], 'Welcome back, Admin!');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not log in. Please try again.', 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]], 'OK');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token !== null) {
            $token->delete();
        }

        return $this->success(null, 'Logged out successfully.');
    }
}
