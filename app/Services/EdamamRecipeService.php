<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EdamamRecipeService
{
    private $appId;
    private $appKey;
    private $recipeApiUrl;
    private $timeout;
    private $userId;

    public function __construct()
    {
        $this->appId = config('services.edamam.recipe_app_id');
        $this->appKey = config('services.edamam.recipe_app_key');
        $this->recipeApiUrl = config('services.edamam.recipe_api_url', 'https://api.edamam.com/api/recipes/v2');
        $this->timeout = config('services.edamam.timeout', 10);
        $this->userId = 'demo-user'; // Required by Edamam
    }

    /**
     * Search recipes and extract ingredients
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function searchRecipesForIngredients(string $query, int $limit = 20): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Edamam-Account-User' => $this->userId
                ])
                ->get($this->recipeApiUrl, [
                    'type' => 'public',
                    'q' => $query,
                    'app_id' => $this->appId,
                    'app_key' => $this->appKey,
                    'from' => 0,
                    'to' => $limit
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->extractIngredientsFromRecipes($data, $query);
            }

            Log::error('Edamam Recipe Search API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Edamam Recipe Search Exception', [
                'message' => $e->getMessage(),
                'query' => $query
            ]);

            return [];
        }
    }

    /**
     * Extract and filter ingredients from recipe search results
     *
     * @param array $recipeData
     * @param string $searchQuery
     * @return array
     */
    private function extractIngredientsFromRecipes(array $recipeData, string $searchQuery): array
    {
        $ingredients = [];
        $searchQueryLower = strtolower($searchQuery);

        if (!isset($recipeData['hits']) || !is_array($recipeData['hits'])) {
            return [];
        }

        foreach ($recipeData['hits'] as $hit) {
            if (!isset($hit['recipe']['ingredientLines']) || !is_array($hit['recipe']['ingredientLines'])) {
                continue;
            }

            foreach ($hit['recipe']['ingredientLines'] as $ingredientLine) {
                $ingredientLineLower = strtolower(trim($ingredientLine));
                
                // Only include ingredients that contain the search query
                if (strpos($ingredientLineLower, $searchQueryLower) !== false) {
                    $ingredients[] = trim($ingredientLine);
                }
            }
        }

        // Remove duplicates and return unique ingredients
        return array_values(array_unique($ingredients));
    }

    /**
     * Get paginated recipe search results for ingredients
     *
     * @param string $query
     * @param int $from
     * @param int $to
     * @return array
     */
    public function getPaginatedRecipeIngredients(string $query, int $from = 0, int $to = 20): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Edamam-Account-User' => $this->userId
                ])
                ->get($this->recipeApiUrl, [
                    'type' => 'public',
                    'q' => $query,
                    'app_id' => $this->appId,
                    'app_key' => $this->appKey,
                    'from' => $from,
                    'to' => $to
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $ingredients = $this->extractIngredientsFromRecipes($data, $query);
                
                return [
                    'ingredients' => $ingredients,
                    'has_more' => isset($data['_links']['next']),
                    'next_from' => $to,
                    'total_results' => $data['count'] ?? 0
                ];
            }

            Log::error('Edamam Paginated Recipe Search API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query,
                'from' => $from,
                'to' => $to
            ]);

            return [
                'ingredients' => [],
                'has_more' => false,
                'next_from' => $from,
                'total_results' => 0
            ];
        } catch (Exception $e) {
            Log::error('Edamam Paginated Recipe Search Exception', [
                'message' => $e->getMessage(),
                'query' => $query,
                'from' => $from,
                'to' => $to
            ]);

            return [
                'ingredients' => [],
                'has_more' => false,
                'next_from' => $from,
                'total_results' => 0
            ];
        }
    }
}