<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class EdamamFoodService
{
    private EdamamConfigService $configService;
    private array $config;
    private int $cacheTimeout = 3600; // 1 hour cache

    public function __construct(EdamamConfigService $configService)
    {
        $this->configService = $configService;
        $this->config = $this->configService->getFoodConfig();
    }

    /**
     * Get autocomplete suggestions for ingredient search
     *
     * @param string $query Search query
     * @param int $limit Maximum number of suggestions (default: 10)
     * @return array
     * @throws Exception
     */
    public function autocomplete(string $query, int $limit = 10): array
    {
        // Debug log to see if method is being called
        Log::info('EdamamFoodService::autocomplete called', [
            'query' => $query,
            'limit' => $limit
        ]);
        
        if (empty(trim($query))) {
            throw new Exception('Search query cannot be empty');
        }

        if ($limit < 1 || $limit > 20) {
            throw new Exception('Limit must be between 1 and 20');
        }

        $cacheKey = $this->getCacheKey('autocomplete', $query, $limit);
        
        // Debug log to check cache key
        Log::info('Cache key generated', [
            'cache_key' => $cacheKey,
            'cache_exists' => Cache::has($cacheKey)
        ]);
        
        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($query, $limit) {
            try {
                $response = $this->makeAutocompleteRequest($query, $limit);
                return $this->formatAutocompleteResponse($response, $query);
            } catch (Exception $e) {
                Log::error('Edamam Food Autocomplete Error', [
                    'query' => $query,
                    'limit' => $limit,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Search for food items with detailed information
     *
     * @param string $query Search query
     * @param array $options Search options (category, brand, etc.)
     * @return array
     * @throws Exception
     */
    public function searchFood(string $query, array $options = []): array
    {
        if (empty(trim($query))) {
            throw new Exception('Search query cannot be empty');
        }

        $cacheKey = $this->getCacheKey('search', $query, $options);
        
        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($query, $options) {
            try {
                $response = $this->makeFoodSearchRequest($query, $options);
                return $this->formatFoodSearchResponse($response, $query);
            } catch (Exception $e) {
                Log::error('Edamam Food Search Error', [
                    'query' => $query,
                    'options' => $options,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Get suggestions for ingredient alternatives
     *
     * @param string $ingredient Original ingredient
     * @param array $filters Dietary filters (vegetarian, vegan, etc.)
     * @return array
     * @throws Exception
     */
    public function getAlternatives(string $ingredient, array $filters = []): array
    {
        if (empty(trim($ingredient))) {
            throw new Exception('Ingredient cannot be empty');
        }

        $cacheKey = $this->getCacheKey('alternatives', $ingredient, $filters);
        
        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($ingredient, $filters) {
            try {
                // Search for similar ingredients with filters
                $searchOptions = array_merge($filters, ['limit' => 15]);
                $response = $this->makeFoodSearchRequest($ingredient, $searchOptions);
                return $this->formatAlternativesResponse($response, $ingredient);
            } catch (Exception $e) {
                Log::error('Edamam Food Alternatives Error', [
                    'ingredient' => $ingredient,
                    'filters' => $filters,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Validate ingredient format and suggest corrections
     *
     * @param string $ingredient Ingredient string to validate
     * @return array
     */
    public function validateIngredient(string $ingredient): array
    {
        $validation = [
            'is_valid' => true,
            'suggestions' => [],
            'warnings' => [],
            'formatted' => trim($ingredient)
        ];

        $trimmed = trim($ingredient);
        
        if (empty($trimmed)) {
            $validation['is_valid'] = false;
            $validation['warnings'][] = 'Ingredient cannot be empty';
            return $validation;
        }

        // Check for common formatting issues
        if (strlen($trimmed) < 2) {
            $validation['warnings'][] = 'Ingredient name is very short';
        }

        if (strlen($trimmed) > 200) {
            $validation['warnings'][] = 'Ingredient name is very long';
            $validation['formatted'] = substr($trimmed, 0, 200);
        }

        // Check for numbers at the beginning (quantity indicators)
        if (preg_match('/^\d+/', $trimmed)) {
            $validation['warnings'][] = 'Ingredient appears to include quantity. Consider separating quantity from ingredient name.';
        }

        // Check for multiple ingredients in one string
        if (preg_match('/\band\b|\bor\b|,/', $trimmed)) {
            $validation['warnings'][] = 'Multiple ingredients detected. Consider splitting into separate entries.';
        }

        return $validation;
    }

    /**
     * Make autocomplete API request
     *
     * @param string $query
     * @param int $limit
     * @return array
     * @throws Exception
     */
    private function makeAutocompleteRequest(string $query, int $limit): array
    {
        $queryParams = [
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key'],
            'q' => $query,
            'limit' => $limit
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
            throw new Exception("Edamam Food API Error: {$errorMessage}");
        }

        $responseData = $response->json();
        
        // Log the actual API response for debugging
        Log::info('Edamam Autocomplete Raw Response', [
            'query' => $query,
            'response_data' => $responseData,
            'response_count' => is_array($responseData) ? count($responseData) : 0,
            'response_type' => gettype($responseData),
            'is_empty' => empty($responseData)
        ]);

        return $responseData;
    }

    /**
     * Make food search API request
     *
     * @param string $query
     * @param array $options
     * @return array
     * @throws Exception
     */
    private function makeFoodSearchRequest(string $query, array $options): array
    {
        $queryParams = [
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key'],
            'ingr' => $query
        ];

        // Add optional parameters
        if (isset($options['limit'])) {
            $queryParams['limit'] = min($options['limit'], 50);
        }

        if (isset($options['category'])) {
            $queryParams['category'] = $options['category'];
        }

        if (isset($options['brand'])) {
            $queryParams['brand'] = $options['brand'];
        }

        if (isset($options['health'])) {
            $queryParams['health'] = $options['health'];
        }

        $headers = $this->configService->getDefaultHeaders();
        $retryConfig = $this->configService->getRetryConfig();
        $timeout = $this->configService->getRequestTimeout();

        // Use parser endpoint for food search
        $searchUrl = str_replace('auto-complete', 'api/food-database/v2/parser', $this->config['api_url']);

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->retry($retryConfig['max_retries'], $retryConfig['retry_delay'])
            ->get($searchUrl, $queryParams);

        if (!$response->successful()) {
            $errorMessage = $this->parseErrorResponse($response);
            throw new Exception("Edamam Food Search API Error: {$errorMessage}");
        }

        return $response->json();
    }

    /**
     * Format autocomplete response
     *
     * @param array $response
     * @param string $query
     * @return array
     */
    private function formatAutocompleteResponse(array $response, string $query): array
    {
        $suggestions = $response ?? [];
        
        // Log the formatting process
        Log::info('Formatting Autocomplete Response', [
            'query' => $query,
            'raw_suggestions' => $suggestions,
            'suggestions_count' => count($suggestions),
            'suggestions_type' => gettype($suggestions)
        ]);
        
        // Transform autocomplete suggestions to match frontend expectations
        // Frontend expects hints array with food objects
        $hints = array_map(function ($suggestion, $index) {
            return [
                'food' => [
                    'foodId' => 'autocomplete_' . $index, // Generate a temporary ID
                    'label' => $suggestion,
                    'brand' => null,
                    'category' => null,
                    'categoryLabel' => null,
                    'nutrients' => [],
                    'image' => null
                ]
            ];
        }, $suggestions, array_keys($suggestions));
        
        // Log the formatted result
        Log::info('Formatted Autocomplete Response', [
            'query' => $query,
            'hints_count' => count($hints),
            'formatted_hints' => $hints
        ]);
        
        return [
            'text' => $query,
            'parsed' => [],
            'hints' => $hints,
            '_links' => [],
            'searchMetadata' => [
                'searchedAt' => now()->toISOString(),
                'source' => 'Edamam Autocomplete API',
                'version' => '1.0',
                'totalResults' => count($hints)
            ]
        ];
    }

    /**
     * Format food search response
     *
     * @param array $response
     * @param string $query
     * @return array
     */
    private function formatFoodSearchResponse(array $response, string $query): array
    {
        $hints = $response['hints'] ?? [];
        
        return [
            'success' => true,
            'query' => $query,
            'count' => count($hints),
            'foods' => array_map(function ($hint) {
                $food = $hint['food'] ?? [];
                return [
                    'food_id' => $food['foodId'] ?? null,
                    'label' => $food['label'] ?? '',
                    'brand' => $food['brand'] ?? null,
                    'category' => $food['category'] ?? null,
                    'category_label' => $food['categoryLabel'] ?? null,
                    'nutrients' => $this->formatBasicNutrients($food['nutrients'] ?? []),
                    'measures' => $this->formatMeasures($hint['measures'] ?? [])
                ];
            }, $hints)
        ];
    }

    /**
     * Format alternatives response
     *
     * @param array $response
     * @param string $ingredient
     * @return array
     */
    private function formatAlternativesResponse(array $response, string $ingredient): array
    {
        $hints = $response['hints'] ?? [];
        
        // Filter out exact matches and sort by relevance
        $alternatives = array_filter($hints, function ($hint) use ($ingredient) {
            $label = strtolower($hint['food']['label'] ?? '');
            return $label !== strtolower($ingredient);
        });

        return [
            'success' => true,
            'original' => $ingredient,
            'count' => count($alternatives),
            'alternatives' => array_map(function ($hint) {
                $food = $hint['food'] ?? [];
                return [
                    'food_id' => $food['foodId'] ?? null,
                    'label' => $food['label'] ?? '',
                    'brand' => $food['brand'] ?? null,
                    'category' => $food['category'] ?? null,
                    'similarity_score' => $this->calculateSimilarity($food['label'] ?? '', request('original', '')),
                    'nutrients' => $this->formatBasicNutrients($food['nutrients'] ?? [])
                ];
            }, array_slice($alternatives, 0, 10))
        ];
    }

    /**
     * Format basic nutrients
     *
     * @param array $nutrients
     * @return array
     */
    private function formatBasicNutrients(array $nutrients): array
    {
        $basicNutrients = ['ENERC_KCAL', 'PROCNT', 'FAT', 'CHOCDF', 'FIBTG'];
        $formatted = [];
        
        foreach ($basicNutrients as $nutrient) {
            if (isset($nutrients[$nutrient])) {
                $formatted[$nutrient] = [
                    'quantity' => round($nutrients[$nutrient], 2),
                    'unit' => $this->getNutrientUnit($nutrient)
                ];
            }
        }
        
        return $formatted;
    }

    /**
     * Format measures
     *
     * @param array $measures
     * @return array
     */
    private function formatMeasures(array $measures): array
    {
        return array_map(function ($measure) {
            return [
                'uri' => $measure['uri'] ?? null,
                'label' => $measure['label'] ?? '',
                'weight' => $measure['weight'] ?? 0
            ];
        }, $measures);
    }

    /**
     * Highlight matching text in suggestions
     *
     * @param string $text
     * @param string $query
     * @return string
     */
    private function highlightMatch(string $text, string $query): string
    {
        if (empty($query)) {
            return $text;
        }
        
        return preg_replace('/(' . preg_quote($query, '/') . ')/i', '<mark>$1</mark>', $text);
    }

    /**
     * Calculate similarity between two strings
     *
     * @param string $str1
     * @param string $str2
     * @return float
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        return round(similar_text(strtolower($str1), strtolower($str2)) / max(strlen($str1), strlen($str2)) * 100, 1);
    }

    /**
     * Get nutrient unit
     *
     * @param string $nutrient
     * @return string
     */
    private function getNutrientUnit(string $nutrient): string
    {
        $units = [
            'ENERC_KCAL' => 'kcal',
            'PROCNT' => 'g',
            'FAT' => 'g',
            'CHOCDF' => 'g',
            'FIBTG' => 'g'
        ];
        
        return $units[$nutrient] ?? '';
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
        $key = "edamam_food_{$type}_" . md5($query . serialize($params));
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
     * Clear cache for specific query or all food cache
     *
     * @param string|null $query
     * @return bool
     */
    public function clearCache(?string $query = null): bool
    {
        if ($query) {
            $patterns = [
                $this->getCacheKey('autocomplete', $query),
                $this->getCacheKey('search', $query),
                $this->getCacheKey('alternatives', $query)
            ];
            
            foreach ($patterns as $pattern) {
                Cache::forget($pattern);
            }
        } else {
            // Clear all food-related cache
            Cache::flush(); // Note: This clears all cache, consider using tags for more specific clearing
        }
        
        return true;
    }
}