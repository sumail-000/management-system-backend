<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class PasswordResetRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'otp' => [
                'required',
                'string',
                'size:6',
                'regex:/^[0-9]{6}$/', // Only 6 digits
            ],
            'email' => $this->getEmailRules(true),
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
            'password_confirmation' => [
                'required',
                'string',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->checkRateLimit();
        });
    }

    /**
     * Check rate limiting for password reset attempts
     */
    protected function checkRateLimit()
    {
        $key = 'password-reset.' . $this->ip();
        
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            
            throw ValidationException::withMessages([
                'email' => [
                    "Too many password reset attempts. Please try again in {$seconds} seconds."
                ]
            ]);
        }
        
        RateLimiter::hit($key, 3600); // 1 hour
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'otp' => 'verification code',
            'email' => 'email address',
            'password' => 'new password',
            'password_confirmation' => 'password confirmation',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'otp.regex' => 'The verification code must be exactly 6 digits.',
            'password.uncompromised' => 'The password has appeared in a data breach. Please choose a different password.',
        ]);
    }
}