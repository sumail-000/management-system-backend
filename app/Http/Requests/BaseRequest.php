<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        // Log validation failures for security monitoring
        Log::warning('Validation failed', [
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'url' => $this->fullUrl(),
            'method' => $this->method(),
            'errors' => $validator->errors()->toArray(),
            'input' => $this->except(['password', 'password_confirmation', 'current_password'])
        ]);

        throw new HttpResponseException(
            response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Sanitize all input data
        $input = $this->all();
        $sanitized = $this->sanitizeInput($input);
        $this->replace($sanitized);
    }

    /**
     * Recursively sanitize input data
     */
    protected function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }

        if (is_string($data)) {
            // Remove potential XSS vectors
            $data = strip_tags($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            
            // Remove potential SQL injection patterns
            $data = preg_replace('/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i', '', $data);
            
            // Remove JavaScript event handlers
            $data = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $data);
            
            // Remove javascript: protocol
            $data = preg_replace('/javascript\s*:/i', '', $data);
            
            // Remove data: protocol (can be used for XSS)
            $data = preg_replace('/data\s*:/i', '', $data);
            
            return trim($data);
        }

        return $data;
    }

    /**
     * Get common validation rules for text fields
     */
    protected function getTextRules($maxLength = 255, $required = false)
    {
        $rules = [
            'string',
            'max:' . $maxLength,
            'regex:/^[^<>"\'\&]*$/', // No HTML/script characters
        ];

        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }

    /**
     * Get common validation rules for email fields
     */
    protected function getEmailRules($required = false)
    {
        $rules = [
            'email:rfc,dns',
            'max:255',
            'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
        ];

        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }

    /**
     * Get common validation rules for password fields
     */
    protected function getPasswordRules($required = false)
    {
        $rules = [
            'string',
            'min:8',
            'max:128',
            'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
        ];

        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }

    /**
     * Get common validation rules for numeric fields
     */
    protected function getNumericRules($min = null, $max = null, $required = false)
    {
        $rules = ['numeric'];

        if ($min !== null) {
            $rules[] = 'min:' . $min;
        }

        if ($max !== null) {
            $rules[] = 'max:' . $max;
        }

        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }

    /**
     * Get common validation rules for URL fields
     */
    protected function getUrlRules($required = false)
    {
        $rules = [
            'url',
            'max:2048',
            'regex:/^https?:\/\/[^\s<>"\']+$/', // Only allow http/https URLs
        ];

        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }

    /**
     * Get validation messages
     */
    public function messages()
    {
        return [
            'regex' => 'The :attribute contains invalid characters.',
            'email.rfc' => 'The :attribute must be a valid email address.',
            'email.dns' => 'The :attribute must have a valid domain.',
            'password.regex' => 'The :attribute must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'url.regex' => 'The :attribute must be a valid HTTP or HTTPS URL.',
        ];
    }
}