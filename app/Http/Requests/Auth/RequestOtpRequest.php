<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/request-otp
 */
class RequestOtpRequest extends FormRequest
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
            // Indian 10-digit mobile. +91 / spaces are normalized in the service.
            'phone' => ['required', 'string', 'regex:/^(\+91[\s-]?)?[6-9][0-9]{9}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Mobile number is required.',
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number (starting 6-9).',
        ];
    }

    /**
     * Normalized 10-digit phone (without +91 / spaces).
     */
    public function normalizedPhone(): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $this->input('phone', ''));

        // 91 prefix hatao (e.g. 919897012345 -> 9897012345).
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
}
