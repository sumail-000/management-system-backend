<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EdamamNutritionService
{
    private $appId;
    private $appKey;
    private $nutritionApiUrl;
    private $timeout;

    public function __construct()
    {
        $this->appId = config('services.edamam.nutrition_app_id');
        $this->appKey = config('services.edamam.nutrition_app_key');
        $this->nutritionApiUrl = config('services.edamam.nutrition_api_url', 'https://api.edamam.com/api/nutrition-details');
        $this->timeout = config('services.edamam.timeout', 30);
    }

    /**
     * Analyze nutrition for a list of ingredients
     *
     * @param array $ingredients Array of ingredient strings (e.g., ["1 cup tomatoes", "2 tbsp olive oil"])
     * @param string $title Optional title for the recipe
     * @return array|null
     */
    public function analyzeNutrition(array $ingredients, string $title = 'Custom Recipe'): ?array
    {
        try {
            $requestBody = [
                'title' => $title,
                'ingr' => $ingredients
            ];

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json'
                ])
                ->post($this->nutritionApiUrl . '?app_id=' . $this->appId . '&app_key=' . $this->appKey, $requestBody);

            if ($response->successful()) {
                $data = $response->json();
                return $data;
            }

            // Handle specific error for rate limiting
            if ($response->status() == 429) {
                throw new Exception('Edamam API rate limit exceeded. Please try again later.');
            }

            Log::error('Edamam Nutrition Analysis API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'ingredients' => $ingredients
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Edamam Nutrition Analysis Exception', [
                'message' => $e->getMessage(),
                'ingredients' => $ingredients
            ]);

            return null;
        }
    }



    /**
     * Build ingredient string for API from parsed ingredient data
     *
     * @param float $quantity
     * @param string $measure
     * @param string $foodLabel
     * @return string
     */
    public function buildIngredientString(float $quantity, string $measure, string $foodLabel): string
    {
        return "{$quantity} {$measure} {$foodLabel}";
    }

    /**
     * Validate ingredients array format
     *
     * @param array $ingredients
     * @return bool
     */
    public function validateIngredients(array $ingredients): bool
    {
        if (empty($ingredients)) {
            return false;
        }

        foreach ($ingredients as $ingredient) {
            if (!is_string($ingredient) || empty(trim($ingredient))) {
                return false;
            }
        }

        return true;
    }
}
