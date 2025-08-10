<?php

namespace App\Services;

use App\Models\ApiUsage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;

class ApiUsageTracker
{
    protected $startTime;
    protected $requestData;
    protected $endpoint;
    protected $method;
    protected $apiService;
    protected $apiProvider;

    /**
     * Start tracking an API call
     */
    public function startTracking(
        string $apiService,
        string $endpoint,
        array $requestData = [],
        string $apiProvider = 'edamam',
        string $method = 'GET'
    ): self {
        $this->startTime = microtime(true);
        $this->apiService = $apiService;
        $this->endpoint = $endpoint;
        $this->requestData = $this->sanitizeRequestData($requestData);
        $this->apiProvider = $apiProvider;
        $this->method = $method;

        return $this;
    }

    /**
     * End tracking and log the API call
     */
    public function endTracking(
        $response = null,
        bool $success = true,
        string $errorMessage = null
    ): ApiUsage {
        $endTime = microtime(true);
        $responseTime = $endTime - $this->startTime;

        $responseStatus = null;
        $responseSize = null;
        $responseMetadata = [];

        if ($response) {
            if (is_object($response) && method_exists($response, 'status')) {
                // HTTP Response object
                $responseStatus = $response->status();
                $responseSize = strlen($response->body());
                
                // Extract metadata from response
                if ($response->successful()) {
                    $responseData = $response->json();
                    $responseMetadata = $this->extractResponseMetadata($responseData);
                }
            } elseif (is_array($response)) {
                // Array response
                $responseSize = strlen(json_encode($response));
                $responseMetadata = $this->extractResponseMetadata($response);
                $responseStatus = 200; // Assume success for array responses
            }
        }

        return ApiUsage::logApiCall([
            'api_provider' => $this->apiProvider,
            'api_service' => $this->apiService,
            'endpoint' => $this->endpoint,
            'method' => $this->method,
            'request_data' => $this->requestData,
            'response_status' => $responseStatus,
            'response_metadata' => $responseMetadata,
            'response_time' => round($responseTime, 3),
            'request_size' => strlen(json_encode($this->requestData)),
            'response_size' => $responseSize,
            'success' => $success,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Track a complete API call in one method
     */
    public static function track(
        string $apiService,
        string $endpoint,
        callable $apiCall,
        array $requestData = [],
        string $apiProvider = 'edamam',
        string $method = 'GET'
    ) {
        $tracker = new self();
        $tracker->startTracking($apiService, $endpoint, $requestData, $apiProvider, $method);

        try {
            $response = $apiCall();
            $tracker->endTracking($response, true);
            return $response;
        } catch (\Exception $e) {
            $tracker->endTracking(null, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sanitize request data to remove sensitive information
     */
    protected function sanitizeRequestData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'key', 'secret', 'api_key', 'app_key'];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[REDACTED]';
            }
        }

        // Limit the size of request data to prevent huge JSON storage
        $jsonString = json_encode($data);
        if (strlen($jsonString) > 5000) {
            return ['note' => 'Request data too large, truncated for storage'];
        }

        return $data;
    }

    /**
     * Extract useful metadata from API response
     */
    protected function extractResponseMetadata($responseData): array
    {
        $metadata = [];

        if (is_array($responseData)) {
            // Count items in response
            if (isset($responseData['hits'])) {
                $metadata['hits_count'] = count($responseData['hits']);
            }
            if (isset($responseData['parsed'])) {
                $metadata['parsed_count'] = count($responseData['parsed']);
            }
            if (isset($responseData['hints'])) {
                $metadata['hints_count'] = count($responseData['hints']);
            }
            if (isset($responseData['ingredients'])) {
                $metadata['ingredients_count'] = count($responseData['ingredients']);
            }

            // Extract other useful info
            if (isset($responseData['totalNutrients'])) {
                $metadata['nutrients_count'] = count($responseData['totalNutrients']);
            }
            if (isset($responseData['calories'])) {
                $metadata['calories'] = $responseData['calories'];
            }
            if (isset($responseData['totalWeight'])) {
                $metadata['total_weight'] = $responseData['totalWeight'];
            }

            // API-specific metadata
            if (isset($responseData['_links'])) {
                $metadata['has_pagination'] = true;
            }
        }

        return $metadata;
    }

    /**
     * Quick method to track Edamam nutrition API calls
     */
    public static function trackNutritionCall(callable $apiCall, array $ingredients = [])
    {
        return self::track(
            'nutrition_analysis',
            'https://api.edamam.com/api/nutrition-details',
            $apiCall,
            ['ingredients_count' => count($ingredients)],
            'edamam',
            'POST'
        );
    }

    /**
     * Quick method to track Edamam food database API calls
     */
    public static function trackFoodDatabaseCall(callable $apiCall, string $query = '')
    {
        return self::track(
            'food_database',
            'https://api.edamam.com/api/food-database/v2/parser',
            $apiCall,
            ['query' => $query],
            'edamam',
            'GET'
        );
    }

    /**
     * Quick method to track Edamam recipe search API calls
     */
    public static function trackRecipeSearchCall(callable $apiCall, string $query = '')
    {
        return self::track(
            'recipe_search',
            'https://api.edamam.com/api/recipes/v2',
            $apiCall,
            ['query' => $query],
            'edamam',
            'GET'
        );
    }

    /**
     * Quick method to track food parsing API calls
     */
    public static function trackFoodParsingCall(callable $apiCall, string $ingredient = '')
    {
        return self::track(
            'food_parsing',
            'https://api.edamam.com/api/food-database/v2/parser',
            $apiCall,
            ['ingredient' => $ingredient],
            'edamam',
            'GET'
        );
    }

    /**
     * Quick method to track food nutrients API calls
     */
    public static function trackFoodNutrientsCall(callable $apiCall, string $foodId = '')
    {
        return self::track(
            'food_nutrients',
            'https://api.edamam.com/api/food-database/v2/nutrients',
            $apiCall,
            ['food_id' => $foodId],
            'edamam',
            'POST'
        );
    }

    /**
     * Get API usage statistics
     */
    public static function getUsageStats(): array
    {
        return ApiUsage::getDashboardStats();
    }

    /**
     * Get today's API call count
     */
    public static function getTodayCount(): int
    {
        return ApiUsage::getTodayCount();
    }

    /**
     * Get yesterday's API call count
     */
    public static function getYesterdayCount(): int
    {
        return ApiUsage::getYesterdayCount();
    }
}