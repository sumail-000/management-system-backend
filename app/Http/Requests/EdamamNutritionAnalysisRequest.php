<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EdamamNutritionAnalysisRequest extends FormRequest
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
            'ingredients' => 'required|array|min:1',
            'ingredients.*' => 'required|string|max:500',
            'product_id' => 'sometimes|integer|exists:products,id',
            'nutrition_type' => 'sometimes|string|in:cooking,logging',
            'meal_type' => 'sometimes|array',
            'meal_type.*' => 'string|in:Breakfast,Lunch,Dinner,Snack,Teatime',
            'dish_type' => 'sometimes|array',
            'dish_type.*' => 'string|in:Alcohol-cocktail,Biscuits and cookies,Bread,Cereals,Condiments and sauces,Desserts,Drinks,Egg,Fats,Fish,Ice cream and custard,Main course,Meat,Milk,Pancake,Pasta,Pastry,Pies and tarts,Pizza,Preps,Preserve,Salad,Sandwiches,Side dish,Soup,Starter,Sweets',
            'prep' => 'sometimes|string|max:100',
            'yield' => 'sometimes|integer|min:1|max:100',
            'time' => 'sometimes|integer|min:1|max:1440',
            'img' => 'sometimes|url',
            'thumbnail' => 'sometimes|url',
            'source' => 'sometimes|string|max:200',
            'url' => 'sometimes|url',
            'label' => 'sometimes|string|max:200',
            'calories' => 'sometimes|integer|min:0|max:10000',
            'glycemic_index' => 'sometimes|integer|min:0|max:100',
            'ingredients_text' => 'sometimes|string|max:2000'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'ingredients.required' => 'At least one ingredient is required.',
            'ingredients.array' => 'Ingredients must be provided as an array.',
            'ingredients.min' => 'At least one ingredient is required.',

            'ingredients.*.required' => 'Each ingredient cannot be empty.',
            'ingredients.*.string' => 'Each ingredient must be a string.',
            'ingredients.*.max' => 'Each ingredient cannot exceed 500 characters.',
            'product_id.integer' => 'Product ID must be a valid integer.',
            'product_id.exists' => 'The specified product does not exist.',
            'nutrition_type.in' => 'Nutrition type must be either "cooking" or "logging".',
            'meal_type.*.in' => 'Invalid meal type provided.',
            'dish_type.*.in' => 'Invalid dish type provided.',
            'yield.min' => 'Yield must be at least 1.',
            'yield.max' => 'Yield cannot exceed 100.',
            'time.min' => 'Time must be at least 1 minute.',
            'time.max' => 'Time cannot exceed 1440 minutes (24 hours).',
            'calories.min' => 'Calories cannot be negative.',
            'calories.max' => 'Calories cannot exceed 10,000.',
            'glycemic_index.min' => 'Glycemic index cannot be negative.',
            'glycemic_index.max' => 'Glycemic index cannot exceed 100.'
        ];
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Ensure ingredients is always an array
        if (isset($validated['ingredients']) && !is_array($validated['ingredients'])) {
            $validated['ingredients'] = [$validated['ingredients']];
        }
        
        return $validated;
    }
}