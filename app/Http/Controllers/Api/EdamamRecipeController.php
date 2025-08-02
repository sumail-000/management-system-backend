<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EdamamRecipeService;
use App\Http\Requests\EdamamRecipeSearchRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class EdamamRecipeController extends Controller
{
    protected EdamamRecipeService $recipeService;

    public function __construct(EdamamRecipeService $recipeService)
    {
        $this->recipeService = $recipeService;
    }

    /**
     * Search for recipes
     */
    public function search(EdamamRecipeSearchRequest $request): JsonResponse
    {
        try {
            $query = $request->input('q', '');
            
            if (empty(trim($query))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search query is required',
                    'data' => []
                ], 400);
            }

            // Get search filters from request
            $filters = $request->getSearchFilters();
            
            // Add pagination
            $filters = array_merge($filters, $request->getPaginationParams());

            Log::info('Recipe search request', [
                'query' => $query,
                'filters' => $filters
            ]);

            $result = $this->recipeService->searchRecipes($query, $filters);

            return response()->json([
                'success' => true,
                'data' => $result,
                'query' => $query,
                'filters' => $filters
            ]);

        } catch (Exception $e) {
            Log::error('Recipe search error', [
                'query' => $request->input('q'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search recipes: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get recipe details by URI
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $uri = $request->input('uri');
            $id = $request->input('id');
            
            if (empty($uri) && empty($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recipe URI or ID is required'
                ], 400);
            }

            // If ID is provided, convert it to URI format
            if ($id && empty($uri)) {
                $uri = $id;
            }

            Log::info('Recipe details request', ['uri' => $uri]);

            $result = $this->recipeService->getRecipeDetails($uri);

            return response()->json([
                'success' => true,
                'recipe' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Recipe details error', [
                'uri' => $request->input('uri'),
                'id' => $request->input('id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get recipe details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get random recipes
     */
    public function random(Request $request): JsonResponse
    {
        try {
            $count = $request->input('count', 10);
            $filters = $request->only([
                'diet', 'health', 'cuisineType', 'mealType', 'dishType'
            ]);

            Log::info('Random recipes request', [
                'count' => $count,
                'filters' => $filters
            ]);

            $result = $this->recipeService->getRandomRecipes($count, $filters);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Random recipes error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get random recipes: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get recipe suggestions based on ingredients
     */
    public function suggest(Request $request): JsonResponse
    {
        try {
            $ingredients = $request->input('ingredients');
            $limit = $request->input('limit', 10);
            
            if (empty($ingredients)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ingredients are required'
                ], 400);
            }

            // Convert comma-separated string to array if needed
            if (is_string($ingredients)) {
                $ingredients = explode(',', $ingredients);
            }
            
            $ingredients = array_map('trim', $ingredients);
            $ingredients = array_filter($ingredients); // Remove empty values

            Log::info('Recipe suggestions request', [
                'ingredients' => $ingredients,
                'limit' => $limit
            ]);

            $result = $this->recipeService->suggestRecipes($ingredients, $limit);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Recipe suggestions error', [
                'ingredients' => $request->input('ingredients'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get recipe suggestions: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Generate ingredients from product name
     */
    public function generateIngredients(Request $request): JsonResponse
    {
        try {
            $productName = $request->input('product_name');
            $limit = $request->input('limit', 10);
            $servingSize = $request->input('serving_size', 1);
            
            if (empty($productName)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product name is required'
                ], 400);
            }

            Log::info('Generate ingredients request', [
                'product_name' => $productName,
                'limit' => $limit,
                'serving_size' => $servingSize
            ]);

            $result = $this->recipeService->generateIngredientsFromProduct(
                $productName, 
                $limit, 
                $servingSize
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Generate ingredients error', [
                'product_name' => $request->input('product_name'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate ingredients: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get available recipe filters
     */
    public function filters(): JsonResponse
    {
        try {
            $filters = $this->recipeService->getAvailableFilters();

            return response()->json([
                'success' => true,
                'data' => $filters
            ]);

        } catch (Exception $e) {
            Log::error('Recipe filters error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get recipe filters: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Clear recipe cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->recipeService->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Recipe cache cleared successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Clear recipe cache error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear recipe cache: ' . $e->getMessage()
            ], 500);
        }
    }
}