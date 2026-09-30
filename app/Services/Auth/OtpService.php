<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Contracts\OtpRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * OTP based passwordless auth.
 * All business logic lives here — the controller stays thin (SOLID: SRP).
 */
class OtpService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly OtpRepositoryInterface $otps,
    ) {}

    /**
     * @return array{expires_in_seconds: int, resend_available_in: int}
     *
     * @throws ValidationException
     */
    public function requestOtp(string $phone): array
    {
        $expiryMinutes = (int) config('otp.expiry_minutes', 5);
        $maxPerHour = (int) config('otp.max_per_hour', 10);
        $cooldown = (int) config('otp.resend_cooldown_seconds', 30);

        // Rate limit: max N OTPs per hour per number.
        if ($this->otps->countRecentForPhone($phone, 60) >= $maxPerHour) {
            throw ValidationException::withMessages([
                'phone' => ['Too many OTP requests. Please try again in an hour.'],
            ]);
        }

        // Resend cooldown: allow a new OTP only X seconds after the last one.
        $recent = $this->otps->recentForPhone($phone, 1)->first();
        if ($recent !== null && $recent->created_at->diffInSeconds(now()) < $cooldown) {
            $wait = $cooldown - (int) $recent->created_at->diffInSeconds(now());
            throw ValidationException::withMessages([
                'phone' => ["Please wait {$wait} seconds before requesting a new OTP."],
            ]);
        }

        $length = max(4, min(8, (int) config('otp.length', 6)));
        $plain = (string) random_int((int) pow(10, $length - 1), (int) pow(10, $length) - 1);

        DB::transaction(function () use ($phone, $plain, $expiryMinutes): void {
            $this->otps->invalidateLiveForPhone($phone);
            $this->otps->create([
                'phone' => $phone,
                'code_hash' => Hash::make($plain),
                'expires_at' => now()->addMinutes($expiryMinutes),
                'attempts' => 0,
            ]);
        });

        // No real SMS provider — OTP sirf server log me likha jata hai.
        // Response me kabhi OTP mat bhejo (screen pe dev code dikhana band).
        Log::info('DevRatna OTP', ['phone' => $phone, 'otp' => $plain]);

        return [
            'expires_in_seconds' => $expiryMinutes * 60,
            'resend_available_in' => $cooldown,
        ];
    }

    /**
     * @return array{user: User, token: string, is_new: bool}
     *
     * @throws ValidationException
     */
    public function verifyOtp(string $phone, string $otp, ?string $name = null): array
    {
        $maxAttempts = (int) config('otp.max_attempts', 5);

        return DB::transaction(function () use ($phone, $otp, $name, $maxAttempts): array {
            $record = $this->otps->latestLiveForPhone($phone);

            if ($record === null) {
                throw ValidationException::withMessages([
                    'otp' => ['This OTP has expired. Please request a new one.'],
                ]);
            }

            if ($record->attempts >= $maxAttempts) {
                $this->otps->consume($record);
                throw ValidationException::withMessages([
                    'otp' => ['Too many incorrect attempts. Please request a new OTP.'],
                ]);
            }

            $isFixedDevCode = $this->isFixedDevCode($otp);

            if (! $isFixedDevCode && ! Hash::check($otp, $record->code_hash)) {
                $this->otps->incrementAttempts($record);
                throw ValidationException::withMessages([
                    'otp' => ['Incorrect OTP. Please try again.'],
                ]);
            }

            $this->otps->consume($record);

            $user = $this->users->findByPhone($phone);
            $isNew = $user === null;

            if ($isNew) {
                $user = $this->users->create([
                    'phone' => $phone,
                    'name' => $name !== null && trim($name) !== '' ? trim($name) : null,
                    'phone_verified_at' => now(),
                ]);
            } else {
                $updates = [];
                if (($user->name === null || $user->name === '') && $name !== null && trim($name) !== '') {
                    $updates['name'] = trim($name);
                }
                if ($updates !== []) {
                    $user = $this->users->update($user, $updates);
                }
                $user = $this->users->markPhoneVerified($user);
            }

            // Revoke old mobile tokens and issue a new one (single active session).
            $user->tokens()->where('name', 'mobile')->delete();
            $token = $user->createToken('mobile')->plainTextToken;

            /** @var User $fresh */
            $fresh = $user->refresh();

            return ['user' => $fresh, 'token' => $token, 'is_new' => $isNew];
        });
    }

    private function isFixedDevCode(string $otp): bool
    {
        $fixed = (string) config('otp.fixed_code', '');

        if ($fixed === '' || ! app()->environment('local', 'testing')) {
            return false;
        }

        return hash_equals($fixed, $otp);
    }
}
