<?php

namespace App\Http\Controllers;

use App\Http\Requests\EdamamRecipeSearchRequest;
use App\Http\Resources\EdamamRecipeResource;
use App\Http\Resources\EdamamRecipeCollection;
use App\Http\Resources\EdamamErrorResource;
use App\Services\EdamamRecipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EdamamRecipeController extends Controller
{
    protected EdamamRecipeService $recipeService;
    
    public function __construct(EdamamRecipeService $recipeService)
    {
        $this->recipeService = $recipeService;
    }
    
    /**
     * Search recipes
     *
     * @param EdamamRecipeSearchRequest $request
     * @return JsonResponse
     */
    public function search(EdamamRecipeSearchRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Generate cache key for recipe search
            $cacheKey = 'recipe_search_' . md5(json_encode($data));
            
            // Check cache first (cache for 2 hours)
            $result = Cache::remember($cacheKey, 7200, function () use ($data) {
                return $this->recipeService->searchRecipes($data);
            });
            
            if (isset($result['error'])) {
                return response()->json(
                    EdamamErrorResource::fromApiResponse($result),
                    $result['status'] ?? 400
                );
            }
            
            // Check if this is a single recipe detail request
            if ($request->isRecipeDetailRequest()) {
                return response()->json([
                    'success' => true,
                    'data' => new EdamamRecipeResource($result),
                    'meta' => [
                        'type' => 'recipe_detail',
                        'cached' => Cache::has($cacheKey),
                        'timestamp' => now()->toISOString(),
                        'request_id' => $request->header('X-Request-ID', uniqid())
                    ]
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => new EdamamRecipeCollection($result['hits'] ?? []),
                'pagination' => $request->getPaginationParams(),
                'meta' => [
                    'type' => 'recipe_search',
                    'search_params' => $data,
                    'total_results' => $result['count'] ?? 0,
                    'cached' => Cache::has($cacheKey),
                    'timestamp' => now()->toISOString(),
                    'request_id' => $request->header('X-Request-ID', uniqid())
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Recipe search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
    
    /**
     * Get recipe details by URI
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'uri' => 'required|string|url'
            ]);
            
            $uri = $request->input('uri');
            
            // Generate cache key for recipe details
            $cacheKey = 'recipe_detail_' . md5($uri);
            
            // Check cache first (cache for 24 hours)
            $result = Cache::remember($cacheKey, 86400, function () use ($uri) {
                return $this->recipeService->getRecipeDetails($uri);
            });
            
            if (isset($result['error'])) {
                return response()->json(
                    EdamamErrorResource::fromApiResponse($result),
                    $result['status'] ?? 400
                );
            }
            
            // Extract the first recipe from hits
            $recipe = $result['hits'][0] ?? null;
            
            if (!$recipe) {
                return response()->json(
                    EdamamErrorResource::create('not_found', 'Recipe not found', 404),
                    404
                );
            }
            
            return response()->json([
                'success' => true,
                'data' => new EdamamRecipeResource($recipe),
                'meta' => [
                    'uri' => $uri,
                    'cached' => Cache::has($cacheKey),
                    'timestamp' => now()->toISOString(),
                    'request_id' => $request->header('X-Request-ID', uniqid())
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Recipe detail retrieval failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'uri' => $request->input('uri')
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
    
    /**
     * Get random recipes
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function random(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'count' => 'sometimes|integer|min:1|max:20',
                'diet' => 'sometimes|string|in:balanced,high-fiber,high-protein,low-carb,low-fat,low-sodium',
                'health' => 'sometimes|string|in:alcohol-cocktail,alcohol-free,celery-free,crustacean-free,dairy-free,DASH,egg-free,fish-free,fodmap-free,gluten-free,immuno-supportive,keto-friendly,kidney-friendly,kosher,low-potassium,low-sugar,lupine-free,Mediterranean,mollusk-free,mustard-free,no-oil-added,paleo,peanut-free,pescatarian,pork-free,red-meat-free,sesame-free,shellfish-free,soy-free,sugar-conscious,sulfite-free,tree-nut-free,vegan,vegetarian,wheat-free',
                'cuisineType' => 'sometimes|string|in:American,Asian,British,Caribbean,Central Europe,Chinese,Eastern Europe,French,Indian,Italian,Japanese,Kosher,Mediterranean,Mexican,Middle Eastern,Nordic,South American,South East Asian',
                'mealType' => 'sometimes|string|in:Breakfast,Dinner,Lunch,Snack,Teatime'
            ]);
            
            $count = $request->input('count', 10);
            $searchParams = [
                'random' => true,
                'to' => $count
            ];
            
            // Add optional filters
            if ($request->has('diet')) {
                $searchParams['diet'] = $request->input('diet');
            }
            if ($request->has('health')) {
                $searchParams['health'] = $request->input('health');
            }
            if ($request->has('cuisineType')) {
                $searchParams['cuisineType'] = $request->input('cuisineType');
            }
            if ($request->has('mealType')) {
                $searchParams['mealType'] = $request->input('mealType');
            }
            
            // Generate cache key for random recipes
            $cacheKey = 'recipe_random_' . md5(json_encode($searchParams));
            
            // Check cache first (cache for 30 minutes)
            $result = Cache::remember($cacheKey, 1800, function () use ($searchParams) {
                return $this->recipeService->searchRecipes('', $searchParams);
            });
            
            if (isset($result['error'])) {
                return response()->json(
                    EdamamErrorResource::fromApiResponse($result),
                    $result['status'] ?? 400
                );
            }
            
            return response()->json([
                'success' => true,
                'data' => new EdamamRecipeCollection($result['hits'] ?? []),
                'meta' => [
                    'type' => 'random_recipes',
                    'count' => $count,
                    'filters' => array_intersect_key($searchParams, array_flip(['diet', 'health', 'cuisineType', 'mealType'])),
                    'cached' => Cache::has($cacheKey),
                    'timestamp' => now()->toISOString(),
                    'request_id' => $request->header('X-Request-ID', uniqid())
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Random recipes retrieval failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
    
    /**
     * Get recipe suggestions based on ingredients
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function suggest(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ingredients' => 'required|array|min:1|max:10',
                'ingredients.*' => 'required|string|max:100',
                'limit' => 'sometimes|integer|min:1|max:20',
                'diet' => 'sometimes|string|in:balanced,high-fiber,high-protein,low-carb,low-fat,low-sodium',
                'health' => 'sometimes|string|in:alcohol-cocktail,alcohol-free,celery-free,crustacean-free,dairy-free,DASH,egg-free,fish-free,fodmap-free,gluten-free,immuno-supportive,keto-friendly,kidney-friendly,kosher,low-potassium,low-sugar,lupine-free,Mediterranean,mollusk-free,mustard-free,no-oil-added,paleo,peanut-free,pescatarian,pork-free,red-meat-free,sesame-free,shellfish-free,soy-free,sugar-conscious,sulfite-free,tree-nut-free,vegan,vegetarian,wheat-free'
            ]);
            
            $ingredients = $request->input('ingredients');
            $limit = $request->input('limit', 10);
            
            $searchParams = [
                'q' => implode(' ', $ingredients),
                'to' => $limit
            ];
            
            // Add optional filters
            if ($request->has('diet')) {
                $searchParams['diet'] = $request->input('diet');
            }
            if ($request->has('health')) {
                $searchParams['health'] = $request->input('health');
            }
            
            // Generate cache key for recipe suggestions
            $cacheKey = 'recipe_suggest_' . md5(json_encode($searchParams));
            
            // Check cache first (cache for 1 hour)
            $result = Cache::remember($cacheKey, 3600, function () use ($searchParams) {
                $query = $searchParams['q'] ?? '';
                unset($searchParams['q']);
                return $this->recipeService->searchRecipes($query, $searchParams);
            });
            
            if (isset($result['error'])) {
                return response()->json(
                    EdamamErrorResource::fromApiResponse($result),
                    $result['status'] ?? 400
                );
            }
            
            return response()->json([
                'success' => true,
                'data' => new EdamamRecipeCollection($result['hits'] ?? []),
                'meta' => [
                    'type' => 'recipe_suggestions',
                    'ingredients' => $ingredients,
                    'limit' => $limit,
                    'cached' => Cache::has($cacheKey),
                    'timestamp' => now()->toISOString(),
                    'request_id' => $request->header('X-Request-ID', uniqid())
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Recipe suggestions failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ingredients' => $request->input('ingredients')
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
    
    /**
     * Generate ingredients from product name using recipe search
     * This method searches for recipes matching the product name and extracts ingredients
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateIngredients(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_name' => 'required|string|max:200',
                'limit' => 'sometimes|integer|min:1|max:10',
                'serving_size' => 'sometimes|integer|min:1|max:20'
            ]);
            
            $productName = trim($request->input('product_name'));
            $limit = $request->input('limit', 5);
            $servingSize = $request->input('serving_size', 4);
            
            Log::info('Generating ingredients from product name', [
                'product_name' => $productName,
                'limit' => $limit,
                'serving_size' => $servingSize,
                'request_id' => $request->header('X-Request-ID', uniqid())
            ]);
            
            // Search for recipes using the product name
            $searchParams = [
                'q' => $productName,
                'to' => $limit,
                'from' => 0
            ];
            
            // Generate cache key for ingredient generation
            $cacheKey = 'ingredients_generate_' . md5(json_encode([$productName, $limit, $servingSize]));
            
            // Check cache first (cache for 2 hours)
            $result = Cache::remember($cacheKey, 7200, function () use ($searchParams, $productName) {
                return $this->recipeService->searchRecipes($productName, $searchParams);
            });
            
            if (isset($result['error'])) {
                Log::error('Ingredient generation failed - API error', [
                    'product_name' => $productName,
                    'error' => $result['error'],
                    'status' => $result['status'] ?? 400
                ]);
                
                return response()->json(
                    EdamamErrorResource::fromApiResponse($result),
                    $result['status'] ?? 400
                );
            }
            
            // Extract and process ingredients from recipe results
            $extractedIngredients = $this->extractIngredientsFromRecipes($result['hits'] ?? []);
            
            // Sort ingredients by frequency and importance
            $extractedIngredients = $this->sortIngredientsByImportance($extractedIngredients);
            
            // Limit the number of ingredients returned
            $extractedIngredients = array_slice($extractedIngredients, 0, 25);
            
            $recipesFound = count($result['hits'] ?? []);
            
            Log::info('Ingredients generated successfully', [
                'product_name' => $productName,
                'ingredients_count' => count($extractedIngredients),
                'recipes_found' => $recipesFound,
                'cached' => Cache::has($cacheKey)
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'ingredients' => $extractedIngredients,
                    'recipes_found' => $recipesFound,
                    'product_name' => $productName
                ],
                'meta' => [
                    'type' => 'ingredient_generation',
                    'product_name' => $productName,
                    'serving_size' => $servingSize,
                    'ingredients_count' => count($extractedIngredients),
                    'recipes_analyzed' => $recipesFound,
                    'cached' => Cache::has($cacheKey),
                    'timestamp' => now()->toISOString(),
                    'request_id' => $request->header('X-Request-ID', uniqid())
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Ingredient generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'product_name' => $request->input('product_name')
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
    
    /**
     * Extract ingredients from recipe search results
     *
     * @param array $recipeHits
     * @return array
     */
    private function extractIngredientsFromRecipes(array $recipeHits): array
    {
        $extractedIngredients = [];
        $ingredientFrequency = [];
        
        foreach ($recipeHits as $recipeHit) {
            $recipe = $recipeHit['recipe'] ?? [];
            $ingredients = $recipe['ingredients'] ?? [];
            
            foreach ($ingredients as $ingredient) {
                $ingredientText = trim($ingredient['text'] ?? '');
                $ingredientFood = trim($ingredient['food'] ?? '');
                
                if (!empty($ingredientFood) && strlen($ingredientFood) > 1) {
                    // Clean and normalize ingredient name
                    $ingredientName = $this->cleanIngredientName($ingredientFood);
                    
                    if (!empty($ingredientName) && !$this->isCommonWord($ingredientName)) {
                        $key = strtolower($ingredientName);
                        
                        // Track frequency
                        if (!isset($ingredientFrequency[$key])) {
                            $ingredientFrequency[$key] = 0;
                            $extractedIngredients[$key] = [
                                'id' => uniqid('ing_'),
                                'name' => $ingredientName,
                                'text' => $ingredientText,
                                'category' => $ingredient['foodCategory'] ?? 'Other',
                                'weight' => $ingredient['weight'] ?? null,
                                'measure' => $ingredient['measure'] ?? null,
                                'quantity' => $ingredient['quantity'] ?? null,
                                'foodId' => $ingredient['foodId'] ?? null,
                                'frequency' => 1
                            ];
                        }
                        
                        $ingredientFrequency[$key]++;
                        $extractedIngredients[$key]['frequency'] = $ingredientFrequency[$key];
                    }
                }
            }
        }
        
        return array_values($extractedIngredients);
    }
    
    /**
     * Clean ingredient name by removing common descriptors
     *
     * @param string $name
     * @return string
     */
    private function cleanIngredientName(string $name): string
    {
        // Remove common descriptors and clean the name
        $descriptors = [
            'fresh', 'dried', 'frozen', 'canned', 'organic', 'raw', 'cooked',
            'chopped', 'diced', 'sliced', 'minced', 'grated', 'shredded',
            'large', 'small', 'medium', 'extra', 'whole', 'ground', 'crushed'
        ];
        
        $words = explode(' ', strtolower($name));
        $cleanWords = array_filter($words, function($word) use ($descriptors) {
            return !in_array(trim($word), $descriptors) && strlen(trim($word)) > 1;
        });
        
        return ucwords(implode(' ', $cleanWords));
    }
    
    /**
     * Check if a word is too common to be a useful ingredient
     *
     * @param string $word
     * @return bool
     */
    private function isCommonWord(string $word): bool
    {
        $commonWords = [
            'and', 'or', 'with', 'for', 'the', 'a', 'an', 'to', 'of', 'in',
            'as', 'needed', 'taste', 'serving', 'garnish', 'optional'
        ];
        
        return in_array(strtolower(trim($word)), $commonWords) || strlen(trim($word)) < 2;
    }
    
    /**
     * Sort ingredients by importance (frequency and category)
     *
     * @param array $ingredients
     * @return array
     */
    private function sortIngredientsByImportance(array $ingredients): array
    {
        // Define category importance weights
        $categoryWeights = [
            'Protein' => 10,
            'Vegetables' => 9,
            'Grains' => 8,
            'Dairy' => 7,
            'Fruits' => 6,
            'Fats and Oils' => 5,
            'Spices and Seasonings' => 4,
            'Condiments and Sauces' => 3,
            'Beverages' => 2,
            'Other' => 1
        ];
        
        // Calculate importance score for each ingredient
        foreach ($ingredients as &$ingredient) {
            $categoryWeight = $categoryWeights[$ingredient['category']] ?? 1;
            $frequencyWeight = $ingredient['frequency'] ?? 1;
            $ingredient['importance_score'] = $categoryWeight * $frequencyWeight;
        }
        
        // Sort by importance score (descending) then by frequency (descending)
        usort($ingredients, function($a, $b) {
            if ($a['importance_score'] === $b['importance_score']) {
                return ($b['frequency'] ?? 1) - ($a['frequency'] ?? 1);
            }
            return $b['importance_score'] - $a['importance_score'];
        });
        
        return $ingredients;
    }
    
    /**
     * Get recipe filters and options
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        try {
            $filters = [
                'diet' => [
                    'balanced' => 'Balanced',
                    'high-fiber' => 'High-Fiber',
                    'high-protein' => 'High-Protein',
                    'low-carb' => 'Low-Carb',
                    'low-fat' => 'Low-Fat',
                    'low-sodium' => 'Low-Sodium'
                ],
                'health' => [
                    'alcohol-free' => 'Alcohol-Free',
                    'dairy-free' => 'Dairy-Free',
                    'egg-free' => 'Egg-Free',
                    'gluten-free' => 'Gluten-Free',
                    'keto-friendly' => 'Keto-Friendly',
                    'kosher' => 'Kosher',
                    'low-sugar' => 'Low-Sugar',
                    'paleo' => 'Paleo',
                    'peanut-free' => 'Peanut-Free',
                    'pescatarian' => 'Pescatarian',
                    'soy-free' => 'Soy-Free',
                    'tree-nut-free' => 'Tree-Nut-Free',
                    'vegan' => 'Vegan',
                    'vegetarian' => 'Vegetarian'
                ],
                'cuisineType' => [
                    'American' => 'American',
                    'Asian' => 'Asian',
                    'British' => 'British',
                    'Chinese' => 'Chinese',
                    'French' => 'French',
                    'Indian' => 'Indian',
                    'Italian' => 'Italian',
                    'Japanese' => 'Japanese',
                    'Mediterranean' => 'Mediterranean',
                    'Mexican' => 'Mexican',
                    'Middle Eastern' => 'Middle Eastern'
                ],
                'mealType' => [
                    'Breakfast' => 'Breakfast',
                    'Lunch' => 'Lunch',
                    'Dinner' => 'Dinner',
                    'Snack' => 'Snack',
                    'Teatime' => 'Teatime'
                ],
                'dishType' => [
                    'main course' => 'Main Course',
                    'side dish' => 'Side Dish',
                    'dessert' => 'Dessert',
                    'appetizer' => 'Appetizer',
                    'salad' => 'Salad',
                    'bread' => 'Bread',
                    'breakfast' => 'Breakfast',
                    'soup' => 'Soup',
                    'beverage' => 'Beverage'
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $filters,
                'meta' => [
                    'timestamp' => now()->toISOString()
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Recipe filters retrieval failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
    
    /**
     * Clear recipe search cache
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'sometimes|string|in:search,detail,random,suggest,all'
            ]);
            
            $type = $request->input('type', 'all');
            $patterns = [
                'search' => 'recipe_search_*',
                'detail' => 'recipe_detail_*',
                'random' => 'recipe_random_*',
                'suggest' => 'recipe_suggest_*',
                'all' => 'recipe_*'
            ];
            
            $pattern = $patterns[$type];
            
            // Clear cache entries matching pattern
            $cleared = 0;
            $cacheKeys = Cache::getRedis()->keys($pattern);
            
            foreach ($cacheKeys as $key) {
                if (Cache::forget($key)) {
                    $cleared++;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Cleared {$cleared} recipe cache entries",
                'meta' => [
                    'type' => $type,
                    'pattern' => $pattern,
                    'cleared_count' => $cleared,
                    'timestamp' => now()->toISOString()
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Recipe cache clearing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
}