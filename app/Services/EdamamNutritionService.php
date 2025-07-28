<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EdamamNutritionService
{
    private EdamamConfigService $configService;
    private array $config;

    public function __construct(EdamamConfigService $configService)
    {
        $this->configService = $configService;
        $this->config = $this->configService->getNutritionConfig();
    }

    /**
     * Analyze ingredients and get nutrition data
     *
     * @param array $ingredients Array of ingredient strings
     * @param array $options Additional options (beta, force, kitchen, field)
     * @return array
     * @throws Exception
     */
    public function analyzeIngredients(array $ingredients, array $options = []): array
    {
        if (empty($ingredients)) {
            throw new Exception('Ingredients array cannot be empty');
        }

        $requestData = [
            'title' => 'Nutrition Analysis',
            'ingr' => $ingredients
        ];
        
        try {
            $response = $this->makeApiRequest($requestData, $options);
            return $this->formatNutritionResponse($response);
        } catch (Exception $e) {
            Log::error('Edamam Nutrition API Error', [
                'ingredients' => $ingredients,
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            throw $e;
        }
    }

    /**
     * Analyze a single recipe with ingredients
     *
     * @param string $title Recipe title
     * @param array $ingredients Array of ingredient strings
     * @param int|null $yield Number of servings (optional)
     * @param array $options Additional options
     * @return array
     * @throws Exception
     */
    public function analyzeRecipe(string $title, array $ingredients, ?int $yield = null, array $options = []): array
    {
        if (empty($title)) {
            throw new Exception('Recipe title cannot be empty');
        }

        $requestData = [
            'title' => $title,
            'ingr' => $ingredients
        ];

        if ($yield !== null && $yield > 0) {
            $requestData['yield'] = $yield;
        }

        try {
            $response = $this->makeApiRequest($requestData, $options);
            return $this->formatNutritionResponse($response);
        } catch (Exception $e) {
            Log::error('Edamam Recipe Analysis Error', [
                'title' => $title,
                'ingredients' => $ingredients,
                'yield' => $yield,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get nutrition data for a list of ingredients with portion analysis
     *
     * @param array $ingredientPortions Array of ['ingredient' => string, 'quantity' => string]
     * @param array $options Additional options
     * @return array
     * @throws Exception
     */
    public function analyzePortions(array $ingredientPortions, array $options = []): array
    {
        $ingredients = [];
        foreach ($ingredientPortions as $portion) {
            if (!isset($portion['ingredient']) || !isset($portion['quantity'])) {
                throw new Exception('Each portion must have ingredient and quantity keys');
            }
            $ingredients[] = $portion['quantity'] . ' ' . $portion['ingredient'];
        }

        return $this->analyzeIngredients($ingredients, $options);
    }

    /**
     * Build request data for API call
     *
     * @param array $ingredients
     * @param array $options
     * @return array
     */
    private function buildRequestData(array $ingredients, array $options = []): array
    {
        $requestData = [
            'recipe' => [
                'ingr' => $ingredients
            ]
        ];

        // Add optional recipe metadata if provided
        if (isset($options['title'])) {
            $requestData['recipe']['title'] = $options['title'];
        }

        if (isset($options['yield']) && $options['yield'] > 0) {
            $requestData['recipe']['yield'] = $options['yield'];
        }

        return $requestData;
    }

    /**
     * Make API request to Edamam Nutrition API
     *
     * @param array $requestData
     * @param array $options
     * @return array
     * @throws Exception
     */
    private function makeApiRequest(array $requestData, array $options = []): array
    {
        $queryParams = [
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key']
        ];

        // Add optional query parameters
        if (isset($options['beta']) && $options['beta']) {
            $queryParams['beta'] = 'true';
        }

        if (isset($options['force']) && $options['force']) {
            $queryParams['force'] = 'true';
        }

        if (isset($options['kitchen'])) {
            $queryParams['kitchen'] = $options['kitchen'];
        }

        if (isset($options['field'])) {
            $queryParams['field'] = $options['field'];
        }

        // Get default headers but exclude Edamam-Account-User for nutrition API
        $headers = $this->configService->getDefaultHeaders();
        
        // Remove Edamam-Account-User header as nutrition API doesn't support users
        unset($headers['Edamam-Account-User']);
        
        // Add optional headers
        if (isset($options['account_user'])) {
            $headers['Edamam-Account-User'] = $options['account_user'];
        }

        if (isset($options['if_none_match'])) {
            $headers['If-None-Match'] = $options['if_none_match'];
        }

        if (isset($options['content_language'])) {
            $headers['Content-Language'] = $options['content_language'];
        }

        $retryConfig = $this->configService->getRetryConfig();
        $timeout = $this->configService->getRequestTimeout();

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->retry($retryConfig['max_retries'], $retryConfig['retry_delay'])
            ->post($this->config['api_url'] . '?' . http_build_query($queryParams), $requestData);

        if (!$response->successful()) {
            $errorMessage = $this->parseErrorResponse($response);
            throw new Exception("Edamam API Error: {$errorMessage}");
        }

        return $response->json();
    }

    /**
     * Format nutrition response data
     *
     * @param array $response
     * @return array
     */
    private function formatNutritionResponse(array $response): array
    {
        $formatted = [
            'success' => true,
            'calories' => $response['calories'] ?? 0,
            'totalWeight' => $response['totalWeight'] ?? 0,
            'dietLabels' => $response['dietLabels'] ?? [],
            'healthLabels' => $response['healthLabels'] ?? [],
            'cautions' => $response['cautions'] ?? [],
            'totalNutrients' => [],
            'totalDaily' => [],
            'ingredients' => $response['ingredients'] ?? [],
            'warnings' => [],
            'high_nutrients' => []
        ];

        // Format total nutrients
        if (isset($response['totalNutrients'])) {
            $formatted['totalNutrients'] = $this->formatNutrients($response['totalNutrients']);
        }

        // Format daily values
        if (isset($response['totalDaily'])) {
            $formatted['totalDaily'] = $this->formatNutrients($response['totalDaily']);
        }

        // Identify high nutrients (>50% daily value)
        $formatted['high_nutrients'] = $this->identifyHighNutrients($formatted['totalDaily']);

        // Add warnings for high nutrients
        if (!empty($formatted['high_nutrients'])) {
            $formatted['warnings'][] = 'This recipe contains high levels of: ' . implode(', ', array_keys($formatted['high_nutrients']));
        }

        return $formatted;
    }

    /**
     * Format nutrients data
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
     * Identify nutrients with high daily values (>50%)
     *
     * @param array $dailyNutrients
     * @return array
     */
    private function identifyHighNutrients(array $dailyNutrients): array
    {
        $highNutrients = [];
        
        foreach ($dailyNutrients as $key => $nutrient) {
            if (isset($nutrient['quantity']) && $nutrient['quantity'] > 50) {
                $highNutrients[$key] = [
                    'label' => $nutrient['label'],
                    'percentage' => round($nutrient['quantity'], 1)
                ];
            }
        }

        return $highNutrients;
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
     * Analyze nutrition data (wrapper method for controller compatibility)
     *
     * @param array $data Request data containing ingredients
     * @param array $options Additional options
     * @return array
     * @throws Exception
     */
    public function analyzeNutrition(array $data, array $options = []): array
    {
        if (isset($data['ingr'])) {
            return $this->analyzeIngredients($data['ingr'], $options);
        }
        
        if (isset($data['recipe'])) {
            $recipe = $data['recipe'];
            $title = $recipe['title'] ?? 'Untitled Recipe';
            $ingredients = $recipe['ingr'] ?? [];
            $yield = $recipe['yield'] ?? null;
            
            return $this->analyzeRecipe($title, $ingredients, $yield, $options);
        }
        
        throw new Exception('Invalid data format. Expected "ingr" array or "recipe" object.');
    }

    /**
     * Batch analyze nutrition for multiple ingredient sets
     *
     * @param array $ingredientSets Array of ingredient arrays
     * @param array $options Additional options
     * @return array
     * @throws Exception
     */
    public function analyzeNutritionBatch(array $ingredientSets, array $options = []): array
    {
        if (empty($ingredientSets)) {
            throw new Exception('Ingredient sets array cannot be empty');
        }

        $results = [];
        $errors = [];

        foreach ($ingredientSets as $index => $ingredientSet) {
            try {
                $result = $this->analyzeNutrition($ingredientSet, $options);
                $results[$index] = $result;
            } catch (Exception $e) {
                $errors[$index] = [
                    'error' => $e->getMessage(),
                    'ingredients' => $ingredientSet
                ];
            }
        }

        return [
            'success' => true,
            'results' => $results,
            'errors' => $errors,
            'total_processed' => count($ingredientSets),
            'successful' => count($results),
            'failed' => count($errors)
        ];
    }

    /**
     * Get supported nutrients list
     *
     * @return array
     */
    public function getSupportedNutrients(): array
    {
        return [
            'ENERC_KCAL' => 'Energy (kcal)',
            'FAT' => 'Fat',
            'FASAT' => 'Saturated Fat',
            'FATRN' => 'Trans Fat',
            'FAMS' => 'Monounsaturated Fat',
            'FAPU' => 'Polyunsaturated Fat',
            'CHOCDF' => 'Carbohydrates',
            'FIBTG' => 'Fiber',
            'SUGAR' => 'Sugars',
            'PROCNT' => 'Protein',
            'CHOLE' => 'Cholesterol',
            'NA' => 'Sodium',
            'CA' => 'Calcium',
            'MG' => 'Magnesium',
            'K' => 'Potassium',
            'FE' => 'Iron',
            'ZN' => 'Zinc',
            'P' => 'Phosphorus',
            'VITA_RAE' => 'Vitamin A',
            'VITC' => 'Vitamin C',
            'THIA' => 'Thiamin (B1)',
            'RIBF' => 'Riboflavin (B2)',
            'NIA' => 'Niacin (B3)',
            'VITB6A' => 'Vitamin B6',
            'FOLDFE' => 'Folate equivalent',
            'VITB12' => 'Vitamin B12',
            'VITD' => 'Vitamin D',
            'TOCPHA' => 'Vitamin E',
            'VITK1' => 'Vitamin K'
        ];
    }
}