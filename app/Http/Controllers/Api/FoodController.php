<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EdamamFoodService;
use App\Services\ApiUsageTracker;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FoodController extends Controller
{
    protected $foodService;

    public function __construct(EdamamFoodService $foodService)
    {
        $this->foodService = $foodService;
    }



    /**
     * Parse ingredient to get detailed food data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function parseIngredient(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ingredient' => 'required|string|min:2|max:200'
            ]);

            $ingredient = $request->input('ingredient');
            
            // Track the API call
            $foodData = ApiUsageTracker::trackFoodParsingCall(
                function() use ($ingredient) {
                    return $this->foodService->parseIngredient($ingredient);
                },
                $ingredient
            );

            if (!$foodData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No food data found for the given ingredient',
                    'ingredient' => $ingredient
                ], 404);
            }

            // Return raw Edamam API response directly
            return response()->json($foodData);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse ingredient',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get food nutrients data by food ID
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getFoodNutrients(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'food_id' => 'required|string|max:100'
            ]);

            $foodId = $request->input('food_id');
            
            // Track the API call
            $nutrientsData = ApiUsageTracker::trackFoodNutrientsCall(
                function() use ($foodId) {
                    return $this->foodService->getFoodNutrients($foodId);
                },
                $foodId
            );

            if (!$nutrientsData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No nutrients data found for the given food ID',
                    'food_id' => $foodId
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $nutrientsData,
                'food_id' => $foodId
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get food nutrients',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Search Food Database Parser API - Parallel call to enhance search results
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchFoodDatabase(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2|max:100'
            ]);

            $query = $request->input('q');
            
            // Track the API call
            $foodData = ApiUsageTracker::trackFoodDatabaseCall(
                function() use ($query) {
                    return $this->foodService->searchFoodDatabase($query);
                },
                $query
            );

            if (!$foodData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No food database results found',
                    'query' => $query,
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'query' => $query,
                'data' => $foodData,
                'hints_count' => isset($foodData['hints']) ? count($foodData['hints']) : 0,
                'parsed_count' => isset($foodData['parsed']) ? count($foodData['parsed']) : 0
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search food database',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search recipes - Direct proxy to Edamam recipe search API
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchRecipes(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2|max:100'
            ]);

            $query = $request->input('q');
            
            // Track the API call
            $response = ApiUsageTracker::trackRecipeSearchCall(
                function() use ($request) {
                    // Make direct API call to Edamam Recipe API and return raw response
                    $appId = config('services.edamam.recipe_app_id');
                    $appKey = config('services.edamam.recipe_app_key');
                    $userId = config('services.edamam.recipe_user_id');
                    $recipeApiUrl = config('services.edamam.recipe_api_url', 'https://api.edamam.com/api/recipes/v2');
                    
                    // Build parameters array
                    $params = [
                        'q' => $request->input('q'),
                        'app_id' => $appId,
                        'app_key' => $appKey,
                        'type' => 'public'
                    ];
                    
                    // Log the request for debugging
                    Log::info('Edamam Recipe API Request', [
                        'url' => $recipeApiUrl,
                        'params' => $params,
                        'user_id' => $userId
                    ]);
                    
                    return Http::timeout(30)
                        ->withHeaders([
                            'Edamam-Account-User' => $userId
                        ])
                        ->get($recipeApiUrl, $params);
                },
                $query
            );

            // Log the response for debugging
            Log::info('Edamam Recipe API Response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            if ($response->successful()) {
                // Return the raw Edamam API response
                return response()->json($response->json());
            }

            return response()->json([
                'error' => 'Failed to fetch data from Edamam Recipe API',
                'status' => $response->status(),
                'edamam_response' => $response->body()
            ], $response->status());

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search recipes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}