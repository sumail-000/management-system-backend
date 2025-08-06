<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EdamamFoodService
{
    private $appId;
    private $appKey;
    private $parserUrl;
    private $timeout;

    public function __construct()
    {
        $this->appId = config('services.edamam.food_app_id');
        $this->appKey = config('services.edamam.food_app_key');
        $this->parserUrl = config('services.edamam.food_parser_url', 'https://api.edamam.com/api/food-database/v2/parser');
        $this->timeout = config('services.edamam.timeout', 30);
    }



    /**
     * Parse ingredient to get detailed food data
     *
     * @param string $ingredient
     * @return array|null
     */
    public function parseIngredient(string $ingredient): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get($this->parserUrl, [
                    'ingr' => $ingredient,
                    'app_id' => $this->appId,
                    'app_key' => $this->appKey
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Return data if we have parsed results OR hints with measures
                if ((isset($data['parsed']) && count($data['parsed']) > 0) || 
                    (isset($data['hints']) && count($data['hints']) > 0)) {
                    return $data;
                }
                
                return null;
            }

            Log::error('Edamam Food Parser API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'ingredient' => $ingredient
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Edamam Food Parser Exception', [
                'message' => $e->getMessage(),
                'ingredient' => $ingredient
            ]);

            return null;
        }
    }

    /**
     * Get food nutrients data
     *
     * @param string $foodId
     * @return array|null
     */
    public function getFoodNutrients(string $foodId): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get($this->parserUrl, [
                    'upc' => $foodId,
                    'app_id' => $this->appId,
                    'app_key' => $this->appKey
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Edamam Food Nutrients API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'foodId' => $foodId
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Edamam Food Nutrients Exception', [
                'message' => $e->getMessage(),
                'foodId' => $foodId
            ]);

            return null;
        }
    }

    /**
     * Extract allergens from food data
     *
     * @param array $foodData
     * @return array
     */
    public function extractAllergens(array $foodData): array
    {
        $allergens = [];
        
        if (isset($foodData['parsed'][0]['food']['nutrients'])) {
            $food = $foodData['parsed'][0]['food'];
            
            // Check for common allergens in food labels or categories
            $allergenKeywords = [
                'gluten' => ['wheat', 'gluten', 'barley', 'rye'],
                'dairy' => ['milk', 'dairy', 'cheese', 'butter', 'cream'],
                'nuts' => ['nuts', 'almond', 'walnut', 'peanut', 'cashew'],
                'soy' => ['soy', 'soya', 'tofu'],
                'eggs' => ['egg', 'eggs'],
                'fish' => ['fish', 'salmon', 'tuna', 'cod'],
                'shellfish' => ['shrimp', 'crab', 'lobster', 'shellfish']
            ];
            
            $foodLabel = strtolower($food['label'] ?? '');
            $foodCategory = strtolower($food['category'] ?? '');
            
            foreach ($allergenKeywords as $allergen => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($foodLabel, $keyword) !== false || strpos($foodCategory, $keyword) !== false) {
                        $allergens[] = $allergen;
                        break;
                    }
                }
            }
        }
        
        return array_unique($allergens);
    }

    /**
     * Get available measures for a food item
     *
     * @param array $foodData
     * @return array
     */
    public function getAvailableMeasures(array $foodData): array
    {
        if (isset($foodData['hints'][0]['measures'])) {
            return $foodData['hints'][0]['measures'];
        }
        
        return [];
    }

    /**
     * Search Food Database Parser API with same parameters as recipe search
     * This provides additional ingredient data alongside recipe search
     *
     * @param string $query
     * @return array|null
     */
    public function searchFoodDatabase(string $query): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get($this->parserUrl, [
                    'ingr' => $query,
                    'app_id' => $this->appId,
                    'app_key' => $this->appKey
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Food Database Parser API Response', [
                    'query' => $query,
                    'hints_count' => isset($data['hints']) ? count($data['hints']) : 0,
                    'parsed_count' => isset($data['parsed']) ? count($data['parsed']) : 0
                ]);
                
                return $data;
            }

            Log::error('Food Database Parser Search API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Food Database Parser Search Exception', [
                'message' => $e->getMessage(),
                'query' => $query
            ]);

            return null;
        }
    }
}