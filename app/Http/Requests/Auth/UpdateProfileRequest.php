<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = auth()->user();
        
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z\s\-\'\.]+$/', // Only letters, spaces, hyphens, apostrophes, and dots
            ],
            'email' => array_merge($this->getEmailRules(false), [
                'sometimes',
                'required',
                Rule::unique('users', 'email')->ignore($user->id),
            ]),
            'company' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-\&\.\,\(\)]+$/', // Company name characters
            ],
            'contact_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/', // Phone number format
            ],
            'tax_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9\-]+$/', // Alphanumeric and hyphens only
            ],
            'avatar' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif',
                'max:2048', // 2MB max
                'dimensions:min_width=50,min_height=50,max_width=1000,max_height=1000',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->checkSuspiciousPatterns();
        });
    }

    /**
     * Check for suspicious patterns in profile data
     */
    protected function checkSuspiciousPatterns()
    {
        $name = $this->input('name', '');
        $company = $this->input('company', '');
        
        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/admin/i',
            '/test/i',
            '/bot/i',
            '/script/i',
            '/hack/i',
            '/null/i',
            '/undefined/i',
            '/<script/i',
            '/javascript:/i',
            '/on\w+=/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $name) || preg_match($pattern, $company)) {
                $validator->errors()->add('name', 'The provided information appears to be invalid.');
                break;
            }
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
            'company' => 'company name',
            'contact_number' => 'contact number',
            'tax_id' => 'tax ID',
            'avatar' => 'profile picture',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'name.regex' => 'The name may only contain letters, spaces, hyphens, apostrophes, and dots.',
            'company.regex' => 'The company name contains invalid characters.',
            'contact_number.regex' => 'The contact number format is invalid.',
            'tax_id.regex' => 'The tax ID may only contain letters, numbers, and hyphens.',
            'avatar.dimensions' => 'The profile picture must be between 50x50 and 1000x1000 pixels.',
        ]);
    }
}