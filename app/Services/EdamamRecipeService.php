<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class EdamamRecipeService
{
    private EdamamConfigService $configService;
    private array $config;
    private int $cacheTimeout = 1800; // 30 minutes cache

    public function __construct(EdamamConfigService $configService)
    {
        $this->configService = $configService;
        $this->config = $this->configService->getRecipeConfig();
    }

    /**
     * Search for recipes with various filters
     *
     * @param string $query Search query
     * @param array $filters Search filters
     * @return array
     * @throws Exception
     */
    public function searchRecipes(string $query, array $filters = []): array
    {
        if (empty(trim($query))) {
            throw new Exception('Search query cannot be empty');
        }

        $cacheKey = $this->getCacheKey('search', $query, $filters);
        
        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($query, $filters) {
            try {
                $response = $this->makeRecipeSearchRequest($query, $filters);
                return $this->formatRecipeSearchResponse($response, $query, $filters);
            } catch (Exception $e) {
                Log::error('Edamam Recipe Search Error', [
                    'query' => $query,
                    'filters' => $filters,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Get recipe details by recipe URI
     *
     * @param string $recipeUri Recipe URI from search results
     * @return array
     * @throws Exception
     */
    public function getRecipeDetails(string $recipeUri): array
    {
        if (empty(trim($recipeUri))) {
            throw new Exception('Recipe URI cannot be empty');
        }

        $cacheKey = $this->getCacheKey('details', $recipeUri);
        
        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($recipeUri) {
            try {
                $response = $this->makeRecipeDetailsRequest($recipeUri);
                return $this->formatRecipeDetailsResponse($response);
            } catch (Exception $e) {
                Log::error('Edamam Recipe Details Error', [
                    'recipe_uri' => $recipeUri,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Search recipes by ingredients
     *
     * @param array $ingredients List of ingredients
     * @param array $filters Additional filters
     * @return array
     * @throws Exception
     */
    public function searchByIngredients(array $ingredients, array $filters = []): array
    {
        if (empty($ingredients)) {
            throw new Exception('Ingredients list cannot be empty');
        }

        $query = implode(' ', $ingredients);
        $filters['q'] = $query;
        
        return $this->searchRecipes($query, $filters);
    }

    /**
     * Get recipe suggestions based on dietary preferences
     *
     * @param array $dietaryPreferences Dietary preferences (vegetarian, vegan, etc.)
     * @param string $mealType Meal type (breakfast, lunch, dinner, snack)
     * @param int $limit Number of suggestions
     * @return array
     * @throws Exception
     */
    public function getRecipeSuggestions(array $dietaryPreferences = [], string $mealType = '', int $limit = 10): array
    {
        $filters = [
            'from' => 0,
            'to' => $limit
        ];

        if (!empty($dietaryPreferences)) {
            $filters['health'] = $dietaryPreferences;
        }

        if (!empty($mealType)) {
            $filters['mealType'] = $mealType;
        }

        // Use a general query for suggestions
        $query = 'healthy recipes';
        
        return $this->searchRecipes($query, $filters);
    }

    /**
     * Search recipes with nutritional constraints
     *
     * @param string $query Search query
     * @param array $nutritionFilters Nutrition filters (calories, fat, etc.)
     * @param array $additionalFilters Additional filters
     * @return array
     * @throws Exception
     */
    public function searchWithNutrition(string $query, array $nutritionFilters = [], array $additionalFilters = []): array
    {
        $filters = array_merge($additionalFilters, $nutritionFilters);
        
        return $this->searchRecipes($query, $filters);
    }

    /**
     * Get available filter options
     *
     * @return array
     */
    public function getAvailableFilters(): array
    {
        return [
            'diet' => [
                'balanced', 'high-fiber', 'high-protein', 'low-carb', 'low-fat', 'low-sodium'
            ],
            'health' => [
                'alcohol-cocktail', 'alcohol-free', 'celery-free', 'crustacean-free', 'dairy-free',
                'DASH', 'egg-free', 'fish-free', 'fodmap-free', 'gluten-free', 'immuno-supportive',
                'keto-friendly', 'kidney-friendly', 'kosher', 'low-potassium', 'low-sugar',
                'lupine-free', 'Mediterranean', 'mollusk-free', 'mustard-free', 'no-oil-added',
                'paleo', 'peanut-free', 'pescatarian', 'pork-free', 'red-meat-free', 'sesame-free',
                'shellfish-free', 'soy-free', 'sugar-conscious', 'sulfite-free', 'tree-nut-free',
                'vegan', 'vegetarian', 'wheat-free'
            ],
            'cuisineType' => [
                'American', 'Asian', 'British', 'Caribbean', 'Central Europe', 'Chinese',
                'Eastern Europe', 'French', 'Indian', 'Italian', 'Japanese', 'Kosher',
                'Mediterranean', 'Mexican', 'Middle Eastern', 'Nordic', 'South American',
                'South East Asian'
            ],
            'mealType' => [
                'Breakfast', 'Dinner', 'Lunch', 'Snack', 'Teatime'
            ],
            'dishType' => [
                'Biscuits and cookies', 'Bread', 'Cereals', 'Condiments and sauces', 'Desserts',
                'Drinks', 'Main course', 'Pancake', 'Preps', 'Preserve', 'Salad', 'Sandwiches',
                'Side dish', 'Soup', 'Starter', 'Sweets'
            ]
        ];
    }

    /**
     * Validate search filters
     *
     * @param array $filters
     * @return array
     */
    public function validateFilters(array $filters): array
    {
        $validation = [
            'is_valid' => true,
            'errors' => [],
            'warnings' => [],
            'cleaned_filters' => []
        ];

        $availableFilters = $this->getAvailableFilters();

        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'from':
                case 'to':
                    if (!is_numeric($value) || $value < 0) {
                        $validation['errors'][] = "{$key} must be a non-negative number";
                        $validation['is_valid'] = false;
                    } else {
                        $validation['cleaned_filters'][$key] = (int)$value;
                    }
                    break;

                case 'calories':
                case 'time':
                    if (!preg_match('/^\d+(-\d+)?$/', $value)) {
                        $validation['errors'][] = "{$key} must be in format 'min' or 'min-max'";
                        $validation['is_valid'] = false;
                    } else {
                        $validation['cleaned_filters'][$key] = $value;
                    }
                    break;

                case 'diet':
                case 'health':
                case 'cuisineType':
                case 'mealType':
                case 'dishType':
                    $validValues = $availableFilters[$key] ?? [];
                    $inputValues = is_array($value) ? $value : [$value];
                    $cleanedValues = [];
                    
                    foreach ($inputValues as $inputValue) {
                        if (in_array($inputValue, $validValues)) {
                            $cleanedValues[] = $inputValue;
                        } else {
                            $validation['warnings'][] = "Invalid {$key} value: {$inputValue}";
                        }
                    }
                    
                    if (!empty($cleanedValues)) {
                        $validation['cleaned_filters'][$key] = count($cleanedValues) === 1 ? $cleanedValues[0] : $cleanedValues;
                    }
                    break;

                default:
                    $validation['cleaned_filters'][$key] = $value;
                    break;
            }
        }

        return $validation;
    }

    /**
     * Make recipe search API request
     *
     * @param string $query
     * @param array $filters
     * @return array
     * @throws Exception
     */
    private function makeRecipeSearchRequest(string $query, array $filters): array
    {
        $queryParams = [
            'type' => 'public',
            'q' => $query,
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key']
        ];

        // Add filters to query parameters
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $queryParams[$key] = $value;
            } else {
                $queryParams[$key] = $value;
            }
        }

        // Set default pagination if not provided
        if (!isset($queryParams['from'])) {
            $queryParams['from'] = 0;
        }
        if (!isset($queryParams['to'])) {
            $queryParams['to'] = 20;
        }

        $headers = $this->configService->getDefaultHeaders();
        $retryConfig = $this->configService->getRetryConfig();
        $timeout = $this->configService->getRequestTimeout();

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->retry($retryConfig['max_retries'], $retryConfig['retry_delay'])
            ->get($this->config['api_url'], $queryParams);

        if (!$response->successful()) {
            $errorMessage = $this->parseErrorResponse($response);
            throw new Exception("Edamam Recipe Search API Error: {$errorMessage}");
        }

        return $response->json();
    }

    /**
     * Make recipe details API request
     *
     * @param string $recipeUri
     * @return array
     * @throws Exception
     */
    private function makeRecipeDetailsRequest(string $recipeUri): array
    {
        $queryParams = [
            'type' => 'public',
            'uri' => $recipeUri,
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key']
        ];

        $headers = $this->configService->getDefaultHeaders();
        $retryConfig = $this->configService->getRetryConfig();
        $timeout = $this->configService->getRequestTimeout();

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->retry($retryConfig['max_retries'], $retryConfig['retry_delay'])
            ->get($this->config['api_url'], $queryParams);

        if (!$response->successful()) {
            $errorMessage = $this->parseErrorResponse($response);
            throw new Exception("Edamam Recipe Details API Error: {$errorMessage}");
        }

        return $response->json();
    }

    /**
     * Format recipe search response
     *
     * @param array $response
     * @param string $query
     * @param array $filters
     * @return array
     */
    private function formatRecipeSearchResponse(array $response, string $query, array $filters): array
    {
        $hits = $response['hits'] ?? [];
        $count = $response['count'] ?? 0;
        $from = $response['from'] ?? 0;
        $to = $response['to'] ?? 0;
        
        return [
            'success' => true,
            'query' => $query,
            'filters' => $filters,
            'pagination' => [
                'from' => $from,
                'to' => $to,
                'count' => $count,
                'total' => $count,
                'has_more' => $to < $count
            ],
            'recipes' => array_map(function ($hit) {
                return $this->formatRecipe($hit['recipe'] ?? []);
            }, $hits),
            '_links' => $response['_links'] ?? []
        ];
    }

    /**
     * Format recipe details response
     *
     * @param array $response
     * @return array
     */
    private function formatRecipeDetailsResponse(array $response): array
    {
        $recipe = $response['recipe'] ?? [];
        
        return [
            'success' => true,
            'recipe' => $this->formatRecipe($recipe)
        ];
    }

    /**
     * Format individual recipe
     *
     * @param array $recipe
     * @return array
     */
    private function formatRecipe(array $recipe): array
    {
        return [
            'uri' => $recipe['uri'] ?? null,
            'label' => $recipe['label'] ?? '',
            'image' => $recipe['image'] ?? null,
            'images' => $recipe['images'] ?? [],
            'source' => $recipe['source'] ?? '',
            'url' => $recipe['url'] ?? '',
            'shareAs' => $recipe['shareAs'] ?? '',
            'yield' => $recipe['yield'] ?? 0,
            'dietLabels' => $recipe['dietLabels'] ?? [],
            'healthLabels' => $recipe['healthLabels'] ?? [],
            'cautions' => $recipe['cautions'] ?? [],
            'ingredientLines' => $recipe['ingredientLines'] ?? [],
            'ingredients' => $this->formatIngredients($recipe['ingredients'] ?? []),
            'calories' => round($recipe['calories'] ?? 0, 2),
            'totalCO2Emissions' => round($recipe['totalCO2Emissions'] ?? 0, 2),
            'co2EmissionsClass' => $recipe['co2EmissionsClass'] ?? null,
            'totalTime' => $recipe['totalTime'] ?? 0,
            'cuisineType' => $recipe['cuisineType'] ?? [],
            'mealType' => $recipe['mealType'] ?? [],
            'dishType' => $recipe['dishType'] ?? [],
            'totalNutrients' => $this->formatNutrients($recipe['totalNutrients'] ?? []),
            'totalDaily' => $this->formatNutrients($recipe['totalDaily'] ?? []),
            'digest' => $this->formatDigest($recipe['digest'] ?? [])
        ];
    }

    /**
     * Format recipe ingredients
     *
     * @param array $ingredients
     * @return array
     */
    private function formatIngredients(array $ingredients): array
    {
        return array_map(function ($ingredient) {
            return [
                'text' => $ingredient['text'] ?? '',
                'quantity' => $ingredient['quantity'] ?? 0,
                'measure' => $ingredient['measure'] ?? null,
                'food' => $ingredient['food'] ?? '',
                'weight' => round($ingredient['weight'] ?? 0, 2),
                'foodCategory' => $ingredient['foodCategory'] ?? null,
                'foodId' => $ingredient['foodId'] ?? null,
                'image' => $ingredient['image'] ?? null
            ];
        }, $ingredients);
    }

    /**
     * Format nutrients
     *
     * @param array $nutrients
     * @return array
     */
    private function formatNutrients(array $nutrients): array
    {
        $formatted = [];
        
        foreach ($nutrients as $key => $nutrient) {
            $formatted[$key] = [
                'label' => $nutrient['label'] ?? '',
                'quantity' => round($nutrient['quantity'] ?? 0, 2),
                'unit' => $nutrient['unit'] ?? ''
            ];
        }
        
        return $formatted;
    }

    /**
     * Format digest information
     *
     * @param array $digest
     * @return array
     */
    private function formatDigest(array $digest): array
    {
        return array_map(function ($item) {
            return [
                'label' => $item['label'] ?? '',
                'tag' => $item['tag'] ?? '',
                'schemaOrgTag' => $item['schemaOrgTag'] ?? null,
                'total' => round($item['total'] ?? 0, 2),
                'hasRDI' => $item['hasRDI'] ?? false,
                'daily' => round($item['daily'] ?? 0, 2),
                'unit' => $item['unit'] ?? '',
                'sub' => isset($item['sub']) ? $this->formatDigest($item['sub']) : []
            ];
        }, $digest);
    }

    /**
     * Generate cache key
     *
     * @param string $type
     * @param string $query
     * @param mixed $params
     * @return string
     */
    private function getCacheKey(string $type, string $query, $params = null): string
    {
        $key = "edamam_recipe_{$type}_" . md5($query . serialize($params));
        return $key;
    }

    /**
     * Parse error response from API
     *
     * @param \Illuminate\Http\Client\Response $response
     * @return string
     */
    private function parseErrorResponse($response): string
    {
        $statusCode = $response->status();
        $body = $response->json();

        if (isset($body['error'])) {
            return "HTTP {$statusCode}: {$body['error']}";
        }

        if (isset($body['message'])) {
            return "HTTP {$statusCode}: {$body['message']}";
        }

        return "HTTP {$statusCode}: Unknown error occurred";
    }

    /**
     * Clear cache for specific query or all recipe cache
     *
     * @param string|null $query
     * @return bool
     */
    public function clearCache(?string $query = null): bool
    {
        if ($query) {
            $patterns = [
                $this->getCacheKey('search', $query),
                $this->getCacheKey('details', $query)
            ];
            
            foreach ($patterns as $pattern) {
                Cache::forget($pattern);
            }
        } else {
            // Clear all recipe-related cache
            Cache::flush(); // Note: This clears all cache, consider using tags for more specific clearing
        }
        
        return true;
    }
}