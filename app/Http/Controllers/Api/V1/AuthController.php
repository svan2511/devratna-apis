<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\OtpService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Mobile OTP auth — thin controller, all logic lives in OtpService.
 */
class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OtpService $otpService) {}

    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->otpService->requestOtp($request->normalizedPhone());

            return $this->success($result, 'OTP sent successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not send the OTP. Please try again later.', 500);
        }
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $outcome = $this->otpService->verifyOtp(
                $request->normalizedPhone(),
                (string) $request->input('otp'),
                $request->input('name') !== null ? (string) $request->input('name') : null,
            );

            $message = $outcome['is_new'] ? 'Welcome to Dev Ratna!' : 'Welcome back!';

            return $this->success(
                [
                    'user' => new UserResource($outcome['user']),
                    'token' => $outcome['token'],
                    'token_type' => 'Bearer',
                    'is_new' => $outcome['is_new'],
                ],
                $message,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Something went wrong during login. Please try again later.', 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->failure('Unauthenticated.', 401);
        }

        return $this->success(['user' => new UserResource($user)], 'OK');
    }

    /**
     * Profile update — naam + default landmark address.
     * Phone yahi se nahi badalta (wahi login identity hai).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->failure('Unauthenticated.', 401);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|min:2|max:100',
            'default_address' => 'nullable|string|min:10|max:500',
        ]);

        if (array_key_exists('name', $data)) {
            $user->name = trim($data['name']);
        }
        if (array_key_exists('default_address', $data)) {
            $addr = $data['default_address'];
            $user->default_address = $addr !== null && trim($addr) !== '' ? trim($addr) : null;
        }
        $user->save();

        return $this->success(['user' => new UserResource($user->fresh())], 'Profile saved.');
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user === null) {
                return $this->failure('Unauthenticated.', 401);
            }

            $token = $user->currentAccessToken();
            if ($token !== null) {
                $token->delete();
            }

            return $this->success(null, 'Logged out successfully.');
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not log out. Please try again.', 500);
        }
    }
}
