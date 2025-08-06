<?php

namespace App\Http\Controllers;

use App\Services\EdamamNutritionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class NutritionController extends Controller
{
    protected $nutritionService;

    public function __construct(EdamamNutritionService $nutritionService)
    {
        $this->nutritionService = $nutritionService;
    }

    /**
     * Analyze nutrition for ingredients
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function analyzeNutrition(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ingredients' => 'required|array|min:1',
            'ingredients.*' => 'required|string|min:1',
            'title' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ingredients = $request->input('ingredients');
        $title = $request->input('title', 'Custom Recipe');

        // Validate ingredients format
        if (!$this->nutritionService->validateIngredients($ingredients)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid ingredients format'
            ], 400);
        }

        try {
            $nutritionData = $this->nutritionService->analyzeNutrition($ingredients, $title);

            if ($nutritionData === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to analyze nutrition. Please check your ingredients and try again.'
                ], 500);
            }

            return response()->json($nutritionData);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while analyzing nutrition',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Build ingredient string helper
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function buildIngredientString(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|numeric|min:0',
            'measure' => 'required|string|min:1',
            'foodLabel' => 'required|string|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $quantity = $request->input('quantity');
        $measure = $request->input('measure');
        $foodLabel = $request->input('foodLabel');

        $ingredientString = $this->nutritionService->buildIngredientString($quantity, $measure, $foodLabel);

        return response()->json([
            'success' => true,
            'data' => [
                'ingredientString' => $ingredientString
            ]
        ]);
    }
}