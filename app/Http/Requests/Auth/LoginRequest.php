<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends BaseRequest
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
            'email' => $this->getEmailRules(true),
            'password' => [
                'required',
                'string',
                'min:1',
                'max:128',
            ],
            'remember' => [
                'nullable',
                'boolean',
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
     * Check rate limiting for login attempts
     */
    protected function checkRateLimit()
    {
        $key = 'login.' . $this->ip();
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            
            throw ValidationException::withMessages([
                'email' => [
                    "Too many login attempts. Please try again in {$seconds} seconds."
                ]
            ]);
        }
        
        RateLimiter::hit($key, 300); // 5 minutes
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'password' => 'password',
        ];
    }
}