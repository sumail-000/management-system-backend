<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EdamamRecipeSearchRequest extends FormRequest
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
            'type' => 'required|string|in:public,user',
            'q' => 'sometimes|string|max:200',
            'app_id' => 'sometimes|string',
            'app_key' => 'sometimes|string',
            'diet' => 'sometimes|array',
            'diet.*' => 'string|in:balanced,high-fiber,high-protein,low-carb,low-fat,low-sodium',
            'health' => 'sometimes|array',
            'health.*' => 'string|in:alcohol-cocktail,alcohol-free,celery-free,crustacean-free,dairy-free,DASH,egg-free,fish-free,fodmap-free,gluten-free,immuno-supportive,keto-friendly,kidney-friendly,kosher,low-potassium,low-sugar,lupine-free,Mediterranean,mollusk-free,mustard-free,no-oil-added,paleo,peanut-free,pescatarian,pork-free,red-meat-free,sesame-free,shellfish-free,soy-free,sugar-conscious,sulfite-free,tree-nut-free,vegan,vegetarian,wheat-free',
            'cuisineType' => 'sometimes|array',
            'cuisineType.*' => 'string|in:American,Asian,British,Caribbean,Central Europe,Chinese,Eastern Europe,French,Indian,Italian,Japanese,Kosher,Mediterranean,Mexican,Middle Eastern,Nordic,South American,South East Asian',
            'mealType' => 'sometimes|array',
            'mealType.*' => 'string|in:Breakfast,Lunch,Dinner,Snack,Teatime',
            'dishType' => 'sometimes|array',
            'dishType.*' => 'string|in:Alcohol-cocktail,Biscuits and cookies,Bread,Cereals,Condiments and sauces,Desserts,Drinks,Egg,Fats,Fish,Ice cream and custard,Main course,Meat,Milk,Pancake,Pasta,Pastry,Pies and tarts,Pizza,Preps,Preserve,Salad,Sandwiches,Side dish,Soup,Starter,Sweets',
            'calories' => 'sometimes|string|regex:/^\d+(-\d+)?$/',
            'time' => 'sometimes|string|regex:/^\d+(-\d+)?$/',
            'imageSize' => 'sometimes|string|in:THUMBNAIL,SMALL,REGULAR,LARGE',
            'glycemicIndex' => 'sometimes|string|regex:/^\d+(-\d+)?$/',
            'nutrients' => 'sometimes|array',
            'nutrients.*' => 'string',
            'excluded' => 'sometimes|array',
            'excluded.*' => 'string|max:100',
            'random' => 'sometimes|boolean',
            'from' => 'sometimes|integer|min:0|max:10000',
            'to' => 'sometimes|integer|min:1|max:100',
            'ingr' => 'sometimes|string|regex:/^\d+(-\d+)?$/',
            'uri' => 'sometimes|string|url',
            'yield' => 'sometimes|string|regex:/^\d+(-\d+)?$/',
            'tag' => 'sometimes|array',
            'tag.*' => 'string|max:50',
            'co2EmissionsClass' => 'sometimes|string|in:A+,A,B,C,D,E,F,G'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Recipe type is required.',
            'type.in' => 'Recipe type must be either "public" or "user".',
            'q.max' => 'Search query cannot exceed 200 characters.',
            'diet.*.in' => 'Invalid diet type provided.',
            'health.*.in' => 'Invalid health label provided.',
            'cuisineType.*.in' => 'Invalid cuisine type provided.',
            'mealType.*.in' => 'Invalid meal type provided.',
            'dishType.*.in' => 'Invalid dish type provided.',
            'calories.regex' => 'Calories must be a number or range (e.g., "100" or "100-500").',
            'time.regex' => 'Time must be a number or range in minutes (e.g., "30" or "30-60").',
            'imageSize.in' => 'Invalid image size. Must be THUMBNAIL, SMALL, REGULAR, or LARGE.',
            'glycemicIndex.regex' => 'Glycemic index must be a number or range (e.g., "50" or "50-70").',
            'excluded.*.max' => 'Each excluded ingredient cannot exceed 100 characters.',
            'from.min' => 'From parameter cannot be negative.',
            'from.max' => 'From parameter cannot exceed 10,000.',
            'to.min' => 'To parameter must be at least 1.',
            'to.max' => 'To parameter cannot exceed 100.',
            'ingr.regex' => 'Ingredient count must be a number or range (e.g., "5" or "5-10").',
            'uri.url' => 'URI must be a valid URL.',
            'yield.regex' => 'Yield must be a number or range (e.g., "4" or "4-6").',
            'tag.*.max' => 'Each tag cannot exceed 50 characters.',
            'co2EmissionsClass.in' => 'Invalid CO2 emissions class. Must be A+, A, B, C, D, E, F, or G.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        if (!$this->has('from')) {
            $this->merge(['from' => 0]);
        }
        
        if (!$this->has('to')) {
            $this->merge(['to' => 20]);
        }
        
        if (!$this->has('imageSize')) {
            $this->merge(['imageSize' => 'REGULAR']);
        }
    }

    /**
     * Get the validated data formatted for API request.
     */
    public function getApiData(): array
    {
        $validated = $this->validated();
        $apiData = [];
        
        // Map all validated data to API parameters
        foreach ($validated as $key => $value) {
            if ($key === 'app_id' || $key === 'app_key') {
                // Skip credentials as they're handled separately
                continue;
            }
            
            $apiData[$key] = $value;
        }
        
        return $apiData;
    }

    /**
     * Check if this is a recipe detail request (has URI).
     */
    public function isRecipeDetailRequest(): bool
    {
        return $this->has('uri') && !empty($this->input('uri'));
    }

    /**
     * Get pagination parameters.
     */
    public function getPaginationParams(): array
    {
        return [
            'from' => $this->input('from', 0),
            'to' => $this->input('to', 20)
        ];
    }
}