<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Dummy OTP mode (dev only)
    |--------------------------------------------------------------------------
    | When true, no real SMS is sent. The OTP is written to the logs and,
    | when APP_DEBUG=true, also returned as `dev_otp` in the API response.
    */
    'dummy' => (bool) env('OTP_DUMMY', true),

    // Fixed dev code accepted in local/testing envs (for emulator testing).
    // Keep it empty in production.
    'fixed_code' => env('OTP_FIXED_CODE', '123456'),

    'length' => (int) env('OTP_LENGTH', 4),
    'expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 5),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN', 30),
    'max_per_hour' => (int) env('OTP_MAX_PER_HOUR', 10),
];
