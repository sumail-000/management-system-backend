<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EdamamRecipeCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = EdamamRecipeResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
                'searchedAt' => now()->toISOString(),
                'source' => 'Edamam Recipe Search API',
                'version' => '2.0',
                'summary' => $this->generateCollectionSummary()
            ],
            'filters' => $this->extractFilters(),
            'suggestions' => $this->generateSuggestions(),
            'aggregated' => $this->generateAggregatedData()
        ];
    }

    /**
     * Generate summary for the collection.
     */
    private function generateCollectionSummary(): array
    {
        $totalCalories = 0;
        $totalTime = 0;
        $totalYield = 0;
        $cuisineTypes = [];
        $mealTypes = [];
        $dishTypes = [];
        $dietLabels = [];
        $healthLabels = [];
        $difficulties = [];
        $costs = [];
        $itemCount = 0;
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            
            $totalCalories += $resource['calories'] ?? 0;
            $totalTime += $resource['totalTime'] ?? 0;
            $totalYield += $resource['yield'] ?? 0;
            
            $cuisineTypes = array_merge($cuisineTypes, $resource['cuisineType'] ?? []);
            $mealTypes = array_merge($mealTypes, $resource['mealType'] ?? []);
            $dishTypes = array_merge($dishTypes, $resource['dishType'] ?? []);
            $dietLabels = array_merge($dietLabels, $resource['dietLabels'] ?? []);
            $healthLabels = array_merge($healthLabels, $resource['healthLabels'] ?? []);
            
            if (isset($resource['difficulty'])) {
                $difficulties[] = $resource['difficulty'];
            }
            if (isset($resource['estimatedCost'])) {
                $costs[] = $resource['estimatedCost'];
            }
            
            $itemCount++;
        }
        
        return [
            'totalRecipes' => $itemCount,
            'averageCalories' => $itemCount > 0 ? round($totalCalories / $itemCount, 0) : 0,
            'averageTime' => $itemCount > 0 ? round($totalTime / $itemCount, 0) : 0,
            'averageYield' => $itemCount > 0 ? round($totalYield / $itemCount, 1) : 0,
            'totalCalories' => round($totalCalories, 0),
            'totalTime' => round($totalTime, 0),
            'popularCuisines' => array_slice(array_keys(array_count_values($cuisineTypes)), 0, 5),
            'popularMealTypes' => array_slice(array_keys(array_count_values($mealTypes)), 0, 5),
            'popularDishTypes' => array_slice(array_keys(array_count_values($dishTypes)), 0, 5),
            'commonDietLabels' => array_unique($dietLabels),
            'commonHealthLabels' => array_unique($healthLabels),
            'difficultyDistribution' => array_count_values($difficulties),
            'costDistribution' => array_count_values($costs)
        ];
    }

    /**
     * Extract filters from the collection.
     */
    private function extractFilters(): array
    {
        $cuisineTypes = [];
        $mealTypes = [];
        $dishTypes = [];
        $dietLabels = [];
        $healthLabels = [];
        $timeRanges = [];
        $calorieRanges = [];
        $difficulties = [];
        $costs = [];
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            
            $cuisineTypes = array_merge($cuisineTypes, $resource['cuisineType'] ?? []);
            $mealTypes = array_merge($mealTypes, $resource['mealType'] ?? []);
            $dishTypes = array_merge($dishTypes, $resource['dishType'] ?? []);
            $dietLabels = array_merge($dietLabels, $resource['dietLabels'] ?? []);
            $healthLabels = array_merge($healthLabels, $resource['healthLabels'] ?? []);
            
            // Time ranges
            $time = $resource['totalTime'] ?? 0;
            if ($time <= 30) $timeRanges[] = 'quick';
            elseif ($time <= 60) $timeRanges[] = 'medium';
            else $timeRanges[] = 'long';
            
            // Calorie ranges
            $calories = $resource['calories'] ?? 0;
            if ($calories <= 300) $calorieRanges[] = 'low';
            elseif ($calories <= 600) $calorieRanges[] = 'medium';
            else $calorieRanges[] = 'high';
            
            if (isset($resource['difficulty'])) {
                $difficulties[] = $resource['difficulty'];
            }
            if (isset($resource['estimatedCost'])) {
                $costs[] = $resource['estimatedCost'];
            }
        }
        
        return [
            'cuisineTypes' => array_values(array_unique($cuisineTypes)),
            'mealTypes' => array_values(array_unique($mealTypes)),
            'dishTypes' => array_values(array_unique($dishTypes)),
            'dietLabels' => array_values(array_unique($dietLabels)),
            'healthLabels' => array_values(array_unique($healthLabels)),
            'timeRanges' => array_values(array_unique($timeRanges)),
            'calorieRanges' => array_values(array_unique($calorieRanges)),
            'difficulties' => array_values(array_unique($difficulties)),
            'costs' => array_values(array_unique($costs))
        ];
    }

    /**
     * Generate search suggestions.
     */
    private function generateSuggestions(): array
    {
        $suggestions = [];
        $ingredients = [];
        $cuisines = [];
        $mealTypes = [];
        $dishTypes = [];
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            
            // Extract ingredients
            foreach ($resource['ingredientLines'] ?? [] as $ingredient) {
                $words = explode(' ', strtolower($ingredient));
                $ingredients = array_merge($ingredients, $words);
            }
            
            $cuisines = array_merge($cuisines, $resource['cuisineType'] ?? []);
            $mealTypes = array_merge($mealTypes, $resource['mealType'] ?? []);
            $dishTypes = array_merge($dishTypes, $resource['dishType'] ?? []);
        }
        
        // Popular ingredients
        $filteredIngredients = array_filter($ingredients, function($ingredient) {
            return strlen($ingredient) > 3 && !in_array($ingredient, [
                'with', 'from', 'fresh', 'large', 'small', 'medium', 'chopped', 'diced'
            ]);
        });
        $ingredientCounts = array_count_values($filteredIngredients);
        arsort($ingredientCounts);
        
        if (!empty($ingredientCounts)) {
            $suggestions['popularIngredients'] = [
                'title' => 'Popular Ingredients',
                'items' => array_slice(array_keys($ingredientCounts), 0, 8)
            ];
        }
        
        // Cuisine suggestions
        $uniqueCuisines = array_unique($cuisines);
        if (!empty($uniqueCuisines)) {
            $suggestions['cuisines'] = [
                'title' => 'Explore Cuisines',
                'items' => array_slice($uniqueCuisines, 0, 6)
            ];
        }
        
        // Meal type suggestions
        $uniqueMealTypes = array_unique($mealTypes);
        if (!empty($uniqueMealTypes)) {
            $suggestions['mealTypes'] = [
                'title' => 'Meal Types',
                'items' => $uniqueMealTypes
            ];
        }
        
        // Quick recipe suggestions
        $suggestions['quickFilters'] = [
            'title' => 'Quick Filters',
            'items' => [
                'under-30-min' => 'Under 30 Minutes',
                'low-calorie' => 'Low Calorie',
                'high-protein' => 'High Protein',
                'vegetarian' => 'Vegetarian',
                'gluten-free' => 'Gluten Free',
                'dairy-free' => 'Dairy Free'
            ]
        ];
        
        // Seasonal suggestions
        $currentMonth = now()->month;
        $seasonalSuggestions = $this->getSeasonalSuggestions($currentMonth);
        if (!empty($seasonalSuggestions)) {
            $suggestions['seasonal'] = [
                'title' => 'Seasonal Favorites',
                'items' => $seasonalSuggestions
            ];
        }
        
        return $suggestions;
    }

    /**
     * Generate aggregated nutritional data.
     */
    private function generateAggregatedData(): array
    {
        $totalCalories = 0;
        $totalProtein = 0;
        $totalCarbs = 0;
        $totalFat = 0;
        $totalFiber = 0;
        $totalSodium = 0;
        $totalTime = 0;
        $itemCount = 0;
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            
            $totalCalories += $resource['calories'] ?? 0;
            $totalTime += $resource['totalTime'] ?? 0;
            
            $nutrients = $resource['totalNutrients'] ?? [];
            $totalProtein += $nutrients['PROCNT']['quantity'] ?? 0;
            $totalCarbs += $nutrients['CHOCDF']['quantity'] ?? 0;
            $totalFat += $nutrients['FAT']['quantity'] ?? 0;
            $totalFiber += $nutrients['FIBTG']['quantity'] ?? 0;
            $totalSodium += $nutrients['NA']['quantity'] ?? 0;
            
            $itemCount++;
        }
        
        if ($itemCount === 0) {
            return [];
        }
        
        $avgCalories = $totalCalories / $itemCount;
        $avgProtein = $totalProtein / $itemCount;
        $avgCarbs = $totalCarbs / $itemCount;
        $avgFat = $totalFat / $itemCount;
        
        return [
            'nutritionalAverages' => [
                'calories' => round($avgCalories, 0),
                'protein' => round($avgProtein, 1),
                'carbs' => round($avgCarbs, 1),
                'fat' => round($avgFat, 1),
                'fiber' => round($totalFiber / $itemCount, 1),
                'sodium' => round($totalSodium / $itemCount, 0)
            ],
            'macronutrientDistribution' => [
                'protein' => $avgCalories > 0 ? round(($avgProtein * 4 / $avgCalories) * 100, 1) : 0,
                'carbs' => $avgCalories > 0 ? round(($avgCarbs * 4 / $avgCalories) * 100, 1) : 0,
                'fat' => $avgCalories > 0 ? round(($avgFat * 9 / $avgCalories) * 100, 1) : 0
            ],
            'timeStatistics' => [
                'averageTime' => round($totalTime / $itemCount, 0),
                'quickRecipes' => $this->countRecipesByTime(0, 30),
                'mediumRecipes' => $this->countRecipesByTime(31, 60),
                'longRecipes' => $this->countRecipesByTime(61, 999)
            ],
            'healthScore' => $this->calculateCollectionHealthScore(),
            'diversityScore' => $this->calculateDiversityScore()
        ];
    }

    /**
     * Count recipes by time range.
     */
    private function countRecipesByTime(int $minTime, int $maxTime): int
    {
        $count = 0;
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            $time = $resource['totalTime'] ?? 0;
            if ($time >= $minTime && $time <= $maxTime) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Calculate overall health score for the collection.
     */
    private function calculateCollectionHealthScore(): int
    {
        $totalScore = 0;
        $itemCount = 0;
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            if (isset($resource['healthScore'])) {
                $totalScore += $resource['healthScore'];
                $itemCount++;
            }
        }
        
        return $itemCount > 0 ? round($totalScore / $itemCount) : 0;
    }

    /**
     * Calculate diversity score based on variety of cuisines, meal types, etc.
     */
    private function calculateDiversityScore(): int
    {
        $cuisines = [];
        $mealTypes = [];
        $dishTypes = [];
        $dietLabels = [];
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            $cuisines = array_merge($cuisines, $resource['cuisineType'] ?? []);
            $mealTypes = array_merge($mealTypes, $resource['mealType'] ?? []);
            $dishTypes = array_merge($dishTypes, $resource['dishType'] ?? []);
            $dietLabels = array_merge($dietLabels, $resource['dietLabels'] ?? []);
        }
        
        $uniqueCuisines = count(array_unique($cuisines));
        $uniqueMealTypes = count(array_unique($mealTypes));
        $uniqueDishTypes = count(array_unique($dishTypes));
        $uniqueDietLabels = count(array_unique($dietLabels));
        
        // Calculate diversity score (0-100)
        $score = min(100, (
            ($uniqueCuisines * 15) +
            ($uniqueMealTypes * 20) +
            ($uniqueDishTypes * 10) +
            ($uniqueDietLabels * 5)
        ));
        
        return round($score);
    }

    /**
     * Get seasonal recipe suggestions.
     */
    private function getSeasonalSuggestions(int $month): array
    {
        $seasonal = [
            // Winter (Dec, Jan, Feb)
            12 => ['soup', 'stew', 'roast', 'hot chocolate', 'comfort food'],
            1 => ['soup', 'stew', 'roast', 'hot chocolate', 'comfort food'],
            2 => ['soup', 'stew', 'roast', 'hot chocolate', 'comfort food'],
            
            // Spring (Mar, Apr, May)
            3 => ['salad', 'asparagus', 'peas', 'fresh herbs', 'light meals'],
            4 => ['salad', 'asparagus', 'peas', 'fresh herbs', 'light meals'],
            5 => ['salad', 'asparagus', 'peas', 'fresh herbs', 'light meals'],
            
            // Summer (Jun, Jul, Aug)
            6 => ['grilled', 'bbq', 'cold soup', 'berries', 'tomatoes'],
            7 => ['grilled', 'bbq', 'cold soup', 'berries', 'tomatoes'],
            8 => ['grilled', 'bbq', 'cold soup', 'berries', 'tomatoes'],
            
            // Fall (Sep, Oct, Nov)
            9 => ['pumpkin', 'apple', 'squash', 'warming spices', 'harvest'],
            10 => ['pumpkin', 'apple', 'squash', 'warming spices', 'harvest'],
            11 => ['pumpkin', 'apple', 'squash', 'warming spices', 'harvest']
        ];
        
        return $seasonal[$month] ?? [];
    }

    /**
     * Add pagination information.
     */
    public function withPagination(array $paginationData): self
    {
        $this->additional([
            'pagination' => [
                'from' => $paginationData['from'] ?? 0,
                'to' => $paginationData['to'] ?? 0,
                'total' => $paginationData['total'] ?? 0,
                'hasMore' => $paginationData['hasMore'] ?? false,
                'nextFrom' => $paginationData['nextFrom'] ?? null
            ]
        ]);
        
        return $this;
    }

    /**
     * Add search context information.
     */
    public function withSearchContext(array $searchContext): self
    {
        $this->additional([
            'searchContext' => [
                'query' => $searchContext['query'] ?? '',
                'filters' => $searchContext['filters'] ?? [],
                'searchType' => $searchContext['searchType'] ?? 'general',
                'executionTime' => $searchContext['executionTime'] ?? null,
                'apiCallsUsed' => $searchContext['apiCallsUsed'] ?? 1
            ]
        ]);
        
        return $this;
    }
}