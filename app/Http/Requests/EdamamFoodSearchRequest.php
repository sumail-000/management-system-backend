<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EdamamFoodSearchRequest extends FormRequest
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
            'ingr' => 'required|string|min:1|max:100',
            'nutrition-type' => 'sometimes|string|in:cooking,logging',
            'category' => 'sometimes|array',
            'category.*' => 'string|in:generic-foods,packaged-foods,generic-meals,fast-foods',
            'health' => 'sometimes|array',
            'health.*' => 'string|in:alcohol-cocktail,alcohol-free,celery-free,crustacean-free,dairy-free,DASH,egg-free,fish-free,fodmap-free,gluten-free,immuno-supportive,keto-friendly,kidney-friendly,kosher,low-potassium,low-sugar,lupine-free,Mediterranean,mollusk-free,mustard-free,no-oil-added,paleo,peanut-free,pescatarian,pork-free,red-meat-free,sesame-free,shellfish-free,soy-free,sugar-conscious,sulfite-free,tree-nut-free,vegan,vegetarian,wheat-free',
            'nutrients' => 'sometimes|array',
            'nutrients.*' => 'string',
            'brand' => 'sometimes|string|max:100',
            'upc' => 'sometimes|string|regex:/^[0-9]{12,14}$/',
            'limit' => 'sometimes|integer|min:1|max:100'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'ingr.required' => 'Ingredient search term is required.',
            'ingr.string' => 'Ingredient search term must be a string.',
            'ingr.min' => 'Ingredient search term must be at least 1 character.',
            'ingr.max' => 'Ingredient search term cannot exceed 100 characters.',
            'nutrition-type.in' => 'Nutrition type must be either "cooking" or "logging".',
            'category.*.in' => 'Invalid category provided.',
            'health.*.in' => 'Invalid health label provided.',
            'brand.max' => 'Brand name cannot exceed 100 characters.',
            'upc.regex' => 'UPC must be a valid 12-14 digit code.',
            'limit.min' => 'Limit must be at least 1.',
            'limit.max' => 'Limit cannot exceed 100.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert nutrition-type to nutrition_type for internal processing
        if ($this->has('nutrition-type')) {
            $this->merge([
                'nutrition_type' => $this->input('nutrition-type')
            ]);
        }
        
        // Set default limit if not provided
        if (!$this->has('limit')) {
            $this->merge(['limit' => 20]);
        }
    }

    /**
     * Get the validated data formatted for API request.
     */
    public function getApiData(): array
    {
        $validated = $this->validated();
        $apiData = [];
        
        // Map validated data to API parameters
        if (isset($validated['ingr'])) {
            $apiData['ingr'] = $validated['ingr'];
        }
        
        if (isset($validated['nutrition_type'])) {
            $apiData['nutrition-type'] = $validated['nutrition_type'];
        }
        
        if (isset($validated['category'])) {
            $apiData['category'] = $validated['category'];
        }
        
        if (isset($validated['health'])) {
            $apiData['health'] = $validated['health'];
        }
        
        if (isset($validated['nutrients'])) {
            $apiData['nutrients'] = $validated['nutrients'];
        }
        
        if (isset($validated['brand'])) {
            $apiData['brand'] = $validated['brand'];
        }
        
        if (isset($validated['upc'])) {
            $apiData['upc'] = $validated['upc'];
        }
        
        if (isset($validated['limit'])) {
            $apiData['limit'] = $validated['limit'];
        }
        
        return $apiData;
    }
}