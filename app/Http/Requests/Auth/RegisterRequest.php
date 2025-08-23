<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends BaseRequest
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
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z\s\-\'\.]+$/', // Only letters, spaces, hyphens, apostrophes, and dots
            ],
            'email' => array_merge($this->getEmailRules(true), [
                'unique:users,email',
            ]),
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
            'company' => [
                'nullable',
                'string',
                'max:255',
            ],
            'contact_number' => [
                'nullable',
                'string',
                'max:20',
            ],
            'tax_id' => [
                'nullable',
                'string',
                'max:50',
            ],
            'membership_plan_id' => [
                'nullable',
                'integer',
                'exists:membership_plans,id',
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
            $this->checkSuspiciousPatterns();
        });
    }

    /**
     * Check rate limiting for registration attempts
     */
    protected function checkRateLimit()
    {
        $key = 'register.' . $this->ip();
        
        // More reasonable rate limiting: 10 attempts per 15 minutes
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            
            throw ValidationException::withMessages([
                'email' => [
                    "Too many registration attempts. Please try again in {$seconds} seconds."
                ]
            ]);
        }
        
        RateLimiter::hit($key, 900); // 15 minutes
    }

    /**
     * Check for suspicious patterns in registration data
     */
    protected function checkSuspiciousPatterns()
    {
        $name = $this->input('name', '');
        $email = $this->input('email', '');
        
        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/admin/i',
            '/test/i',
            '/bot/i',
            '/script/i',
            '/hack/i',
            '/null/i',
            '/undefined/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $name)) {
                throw ValidationException::withMessages([
                    'name' => ['Please enter your real name (avoid placeholder words like "test", "admin", or code terms).']
                ]);
            }
            if (preg_match($pattern, $email)) {
                throw ValidationException::withMessages([
                    'email' => ['Please use a valid email address (avoid placeholder terms like "test" or "admin").']
                ]);
            }
        }
        
        // Check for disposable email domains
        $disposableDomains = [
            '10minutemail.com',
            'tempmail.org',
            'guerrillamail.com',
            'mailinator.com',
            'throwaway.email',
        ];
        
        $emailDomain = substr(strrchr($email, "@"), 1);
        if (in_array(strtolower($emailDomain), $disposableDomains)) {
            throw ValidationException::withMessages([
                'email' => ['Please use a permanent email address.']
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
            'password' => 'password',
            'password_confirmation' => 'password confirmation',
            'terms_accepted' => 'terms and conditions',
            'marketing_emails' => 'marketing email preference',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'name.regex' => 'The name may only contain letters, spaces, hyphens, apostrophes, and dots.',
            'password.uncompromised' => 'The password has appeared in a data breach. Please choose a different password.',
            'terms_accepted.accepted' => 'You must accept the terms and conditions to register.',
        ]);
    }
}