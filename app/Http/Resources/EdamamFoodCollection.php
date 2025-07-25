<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EdamamFoodCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = EdamamFoodResource::class;

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
                'source' => 'Edamam Food Database API',
                'version' => '2.0',
                'summary' => $this->generateCollectionSummary()
            ],
            'filters' => $this->extractFilters(),
            'suggestions' => $this->generateSuggestions()
        ];
    }

    /**
     * Generate summary for the collection.
     */
    private function generateCollectionSummary(): array
    {
        $totalHints = 0;
        $totalParsedFoods = 0;
        $categories = [];
        $brands = [];
        $allergens = [];
        $nutritionGrades = [];
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            $totalHints += count($resource['hints'] ?? []);
            $totalParsedFoods += count($resource['parsed'] ?? []);
            
            // Extract categories from hints
            foreach ($resource['hints'] ?? [] as $hint) {
                if (isset($hint['food']['category'])) {
                    $categories[] = $hint['food']['category'];
                }
                if (isset($hint['food']['brand'])) {
                    $brands[] = $hint['food']['brand'];
                }
                if (isset($hint['allergens'])) {
                    $allergens = array_merge($allergens, $hint['allergens']);
                }
                if (isset($hint['nutritionGrade'])) {
                    $nutritionGrades[] = $hint['nutritionGrade'];
                }
            }
        }
        
        return [
            'totalItems' => $this->collection->count(),
            'totalHints' => $totalHints,
            'totalParsedFoods' => $totalParsedFoods,
            'averageHintsPerItem' => $this->collection->count() > 0 ? round($totalHints / $this->collection->count(), 1) : 0,
            'uniqueCategories' => array_unique($categories),
            'uniqueBrands' => array_unique($brands),
            'commonAllergens' => array_unique($allergens),
            'nutritionGradeDistribution' => array_count_values($nutritionGrades)
        ];
    }

    /**
     * Extract filters from the collection.
     */
    private function extractFilters(): array
    {
        $categories = [];
        $brands = [];
        $dietLabels = [];
        $healthLabels = [];
        $allergens = [];
        $nutritionGrades = [];
        $calorieRanges = [];
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            
            foreach ($resource['hints'] ?? [] as $hint) {
                // Categories
                if (isset($hint['food']['category'])) {
                    $categories[] = $hint['food']['category'];
                }
                
                // Brands
                if (isset($hint['food']['brand'])) {
                    $brands[] = $hint['food']['brand'];
                }
                
                // Diet and health labels
                if (isset($hint['food']['nutrients']['ENERC_KCAL'])) {
                    $calories = $hint['food']['nutrients']['ENERC_KCAL'];
                    if ($calories < 100) $calorieRanges[] = 'low';
                    elseif ($calories < 300) $calorieRanges[] = 'medium';
                    else $calorieRanges[] = 'high';
                }
                
                // Allergens
                if (isset($hint['allergens'])) {
                    $allergens = array_merge($allergens, $hint['allergens']);
                }
                
                // Nutrition grades
                if (isset($hint['nutritionGrade'])) {
                    $nutritionGrades[] = $hint['nutritionGrade'];
                }
            }
        }
        
        return [
            'categories' => array_values(array_unique($categories)),
            'brands' => array_values(array_unique($brands)),
            'allergens' => array_values(array_unique($allergens)),
            'nutritionGrades' => array_values(array_unique($nutritionGrades)),
            'calorieRanges' => array_values(array_unique($calorieRanges))
        ];
    }

    /**
     * Generate search suggestions.
     */
    private function generateSuggestions(): array
    {
        $suggestions = [];
        $categories = [];
        $brands = [];
        $relatedTerms = [];
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            
            // Extract categories and brands for suggestions
            foreach ($resource['hints'] ?? [] as $hint) {
                if (isset($hint['food']['category'])) {
                    $categories[] = $hint['food']['category'];
                }
                if (isset($hint['food']['brand'])) {
                    $brands[] = $hint['food']['brand'];
                }
                
                // Extract related terms from food labels
                if (isset($hint['food']['label'])) {
                    $words = explode(' ', strtolower($hint['food']['label']));
                    $relatedTerms = array_merge($relatedTerms, $words);
                }
            }
        }
        
        // Generate category suggestions
        $uniqueCategories = array_unique($categories);
        if (!empty($uniqueCategories)) {
            $suggestions['categories'] = [
                'title' => 'Browse by Category',
                'items' => array_slice($uniqueCategories, 0, 5)
            ];
        }
        
        // Generate brand suggestions
        $uniqueBrands = array_unique($brands);
        if (!empty($uniqueBrands)) {
            $suggestions['brands'] = [
                'title' => 'Popular Brands',
                'items' => array_slice($uniqueBrands, 0, 5)
            ];
        }
        
        // Generate related search terms
        $filteredTerms = array_filter($relatedTerms, function($term) {
            return strlen($term) > 3 && !in_array($term, ['with', 'from', 'organic', 'natural']);
        });
        $termCounts = array_count_values($filteredTerms);
        arsort($termCounts);
        
        if (!empty($termCounts)) {
            $suggestions['relatedTerms'] = [
                'title' => 'Related Searches',
                'items' => array_slice(array_keys($termCounts), 0, 5)
            ];
        }
        
        // Generate nutrition-based suggestions
        $suggestions['nutritionFilters'] = [
            'title' => 'Filter by Nutrition',
            'items' => [
                'high-protein' => 'High Protein Foods',
                'low-calorie' => 'Low Calorie Options',
                'high-fiber' => 'High Fiber Foods',
                'low-sodium' => 'Low Sodium Choices',
                'sugar-free' => 'Sugar-Free Options'
            ]
        ];
        
        // Generate dietary suggestions
        $suggestions['dietaryFilters'] = [
            'title' => 'Dietary Preferences',
            'items' => [
                'vegetarian' => 'Vegetarian Options',
                'vegan' => 'Vegan Choices',
                'gluten-free' => 'Gluten-Free Foods',
                'dairy-free' => 'Dairy-Free Options',
                'keto-friendly' => 'Keto-Friendly Foods'
            ]
        ];
        
        return $suggestions;
    }

    /**
     * Add pagination information if available.
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

    /**
     * Add nutritional analysis summary.
     */
    public function withNutritionalSummary(): self
    {
        $nutritionalData = $this->calculateNutritionalSummary();
        
        $this->additional([
            'nutritionalSummary' => $nutritionalData
        ]);
        
        return $this;
    }

    /**
     * Calculate nutritional summary from all food items.
     */
    private function calculateNutritionalSummary(): array
    {
        $totalCalories = 0;
        $totalProtein = 0;
        $totalCarbs = 0;
        $totalFat = 0;
        $totalFiber = 0;
        $totalSodium = 0;
        $itemCount = 0;
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            
            foreach ($resource['hints'] ?? [] as $hint) {
                if (isset($hint['food']['nutrients'])) {
                    $nutrients = $hint['food']['nutrients'];
                    $totalCalories += $nutrients['ENERC_KCAL'] ?? 0;
                    $totalProtein += $nutrients['PROCNT'] ?? 0;
                    $totalCarbs += $nutrients['CHOCDF'] ?? 0;
                    $totalFat += $nutrients['FAT'] ?? 0;
                    $totalFiber += $nutrients['FIBTG'] ?? 0;
                    $totalSodium += $nutrients['NA'] ?? 0;
                    $itemCount++;
                }
            }
        }
        
        if ($itemCount === 0) {
            return [];
        }
        
        return [
            'averageCalories' => round($totalCalories / $itemCount, 0),
            'averageProtein' => round($totalProtein / $itemCount, 1),
            'averageCarbs' => round($totalCarbs / $itemCount, 1),
            'averageFat' => round($totalFat / $itemCount, 1),
            'averageFiber' => round($totalFiber / $itemCount, 1),
            'averageSodium' => round($totalSodium / $itemCount, 0),
            'itemsAnalyzed' => $itemCount,
            'macronutrientDistribution' => [
                'protein' => $totalCalories > 0 ? round(($totalProtein * 4 / $totalCalories) * 100, 1) : 0,
                'carbs' => $totalCalories > 0 ? round(($totalCarbs * 4 / $totalCalories) * 100, 1) : 0,
                'fat' => $totalCalories > 0 ? round(($totalFat * 9 / $totalCalories) * 100, 1) : 0
            ]
        ];
    }
}