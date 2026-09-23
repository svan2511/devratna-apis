<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/verify-otp
 */
class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^(\+91[\s-]?)?[6-9][0-9]{9}$/'],
            'otp' => ['required', 'string', 'regex:/^[0-9]{4}$/'],
            // Name is collected on the first login (single-screen mobile flow).
            'name' => ['nullable', 'string', 'min:2', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Mobile number is required.',
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
            'otp.required' => 'OTP is required.',
            'otp.regex' => 'OTP must contain digits only.',
            'name.min' => 'Name must be at least 2 characters.',
        ];
    }

    public function normalizedPhone(): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $this->input('phone', ''));

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
}
