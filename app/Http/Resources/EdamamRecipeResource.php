<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EdamamRecipeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'from' => $this->resource['from'] ?? 0,
            'to' => $this->resource['to'] ?? 0,
            'count' => $this->resource['count'] ?? 0,
            '_links' => $this->formatLinks($this->resource['_links'] ?? []),
            'hits' => $this->formatRecipes($this->resource['hits'] ?? []),
            'searchMetadata' => [
                'searchedAt' => now()->toISOString(),
                'source' => 'Edamam Recipe Search API',
                'version' => '2.0',
                'totalResults' => $this->resource['count'] ?? 0,
                'resultsPerPage' => ($this->resource['to'] ?? 0) - ($this->resource['from'] ?? 0)
            ]
        ];
    }

    /**
     * Format recipes data.
     */
    private function formatRecipes(array $hits): array
    {
        return array_map(function ($hit) {
            $recipe = $hit['recipe'] ?? [];
            
            return [
                'recipe' => $this->formatRecipe($recipe),
                '_links' => $this->formatRecipeLinks($hit['_links'] ?? []),
                'relevanceScore' => $this->calculateRelevanceScore($recipe),
                'nutritionScore' => $this->calculateNutritionScore($recipe),
                'difficultyLevel' => $this->estimateDifficulty($recipe),
                'costEstimate' => $this->estimateCost($recipe)
            ];
        }, $hits);
    }

    /**
     * Format individual recipe.
     */
    private function formatRecipe(array $recipe): array
    {
        return [
            'uri' => $recipe['uri'] ?? null,
            'label' => $recipe['label'] ?? '',
            'image' => $recipe['image'] ?? null,
            'images' => $this->formatImages($recipe['images'] ?? []),
            'source' => $recipe['source'] ?? null,
            'url' => $recipe['url'] ?? null,
            'shareAs' => $recipe['shareAs'] ?? null,
            'yield' => $recipe['yield'] ?? 0,
            'dietLabels' => $recipe['dietLabels'] ?? [],
            'healthLabels' => $recipe['healthLabels'] ?? [],
            'cautions' => $recipe['cautions'] ?? [],
            'ingredientLines' => $recipe['ingredientLines'] ?? [],
            'ingredients' => $this->formatIngredients($recipe['ingredients'] ?? []),
            'calories' => round($recipe['calories'] ?? 0, 0),
            'glycemicIndex' => $recipe['glycemicIndex'] ?? null,
            'inflammatoryIndex' => $recipe['inflammatoryIndex'] ?? null,
            'totalCO2Emissions' => $recipe['totalCO2Emissions'] ?? null,
            'co2EmissionsClass' => $recipe['co2EmissionsClass'] ?? null,
            'totalWeight' => round($recipe['totalWeight'] ?? 0, 1),
            'cuisineType' => $recipe['cuisineType'] ?? [],
            'mealType' => $recipe['mealType'] ?? [],
            'dishType' => $recipe['dishType'] ?? [],
            'instructions' => $recipe['instructions'] ?? [],
            'tags' => $recipe['tags'] ?? [],
            'externalId' => $recipe['externalId'] ?? null,
            'totalNutrients' => $this->formatNutrients($recipe['totalNutrients'] ?? []),
            'totalDaily' => $this->formatNutrients($recipe['totalDaily'] ?? []),
            'digest' => $this->formatDigest($recipe['digest'] ?? []),
            'nutritionSummary' => $this->generateNutritionSummary($recipe),
            'servingInfo' => $this->generateServingInfo($recipe),
            'preparationInfo' => $this->generatePreparationInfo($recipe)
        ];
    }

    /**
     * Format recipe images.
     */
    private function formatImages(array $images): array
    {
        $formatted = [];
        
        foreach ($images as $size => $imageData) {
            $formatted[$size] = [
                'url' => $imageData['url'] ?? null,
                'width' => $imageData['width'] ?? null,
                'height' => $imageData['height'] ?? null
            ];
        }
        
        return $formatted;
    }

    /**
     * Format recipe ingredients.
     */
    private function formatIngredients(array $ingredients): array
    {
        return array_map(function ($ingredient) {
            return [
                'text' => $ingredient['text'] ?? '',
                'quantity' => $ingredient['quantity'] ?? null,
                'measure' => $ingredient['measure'] ?? null,
                'food' => $ingredient['food'] ?? '',
                'weight' => round($ingredient['weight'] ?? 0, 1),
                'foodId' => $ingredient['foodId'] ?? null,
                'foodCategory' => $ingredient['foodCategory'] ?? null,
                'image' => $ingredient['image'] ?? null,
                'nutrients' => $this->formatNutrients($ingredient['nutrients'] ?? []),
                'isMainIngredient' => $this->isMainIngredient($ingredient),
                'allergens' => $this->extractIngredientAllergens($ingredient)
            ];
        }, $ingredients);
    }

    /**
     * Format nutrients data.
     */
    private function formatNutrients(array $nutrients): array
    {
        $formatted = [];
        
        foreach ($nutrients as $key => $nutrient) {
            $formatted[$key] = [
                'label' => $nutrient['label'] ?? $key,
                'quantity' => round($nutrient['quantity'] ?? 0, 2),
                'unit' => $nutrient['unit'] ?? ''
            ];
        }
        
        return $formatted;
    }

    /**
     * Format digest data.
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
                'daily' => round($item['daily'] ?? 0, 1),
                'unit' => $item['unit'] ?? '',
                'sub' => $this->formatDigest($item['sub'] ?? [])
            ];
        }, $digest);
    }

    /**
     * Format links data.
     */
    private function formatLinks(array $links): array
    {
        return [
            'next' => $links['next']['href'] ?? null,
            'self' => $links['self']['href'] ?? null
        ];
    }

    /**
     * Format recipe-specific links.
     */
    private function formatRecipeLinks(array $links): array
    {
        return [
            'self' => $links['self']['href'] ?? null,
            'next' => $links['next']['href'] ?? null
        ];
    }

    /**
     * Calculate relevance score for recipe.
     */
    private function calculateRelevanceScore(array $recipe): float
    {
        $score = 0.5; // Base score
        
        // Boost for recipes with images
        if (!empty($recipe['image'])) {
            $score += 0.2;
        }
        
        // Boost for recipes with instructions
        if (!empty($recipe['instructions'])) {
            $score += 0.1;
        }
        
        // Boost for recipes with detailed nutrition
        if (count($recipe['totalNutrients'] ?? []) > 10) {
            $score += 0.1;
        }
        
        // Boost for recipes with health labels
        if (count($recipe['healthLabels'] ?? []) > 0) {
            $score += 0.1;
        }
        
        return round(min($score, 1.0), 2);
    }

    /**
     * Calculate nutrition score (0-100).
     */
    private function calculateNutritionScore(array $recipe): int
    {
        $score = 50; // Base score
        
        $nutrients = $recipe['totalNutrients'] ?? [];
        $calories = $recipe['calories'] ?? 0;
        $yield = $recipe['yield'] ?? 1;
        $caloriesPerServing = $yield > 0 ? $calories / $yield : $calories;
        
        // Adjust based on calories per serving
        if ($caloriesPerServing < 300) {
            $score += 10;
        } elseif ($caloriesPerServing > 800) {
            $score -= 15;
        }
        
        // Adjust based on fiber content
        $fiber = $nutrients['FIBTG']['quantity'] ?? 0;
        if ($fiber > 10) {
            $score += 15;
        } elseif ($fiber > 5) {
            $score += 10;
        }
        
        // Adjust based on sodium content
        $sodium = $nutrients['NA']['quantity'] ?? 0;
        if ($sodium > 2000) {
            $score -= 20;
        } elseif ($sodium > 1000) {
            $score -= 10;
        }
        
        // Adjust based on health labels
        $healthLabels = $recipe['healthLabels'] ?? [];
        if (in_array('Low Sodium', $healthLabels)) $score += 5;
        if (in_array('High Fiber', $healthLabels)) $score += 5;
        if (in_array('Low Sugar', $healthLabels)) $score += 5;
        
        return max(0, min(100, $score));
    }

    /**
     * Estimate difficulty level.
     */
    private function estimateDifficulty(array $recipe): string
    {
        $ingredients = count($recipe['ingredients'] ?? []);
        $instructions = count($recipe['instructions'] ?? []);
        
        if ($ingredients <= 5 && $instructions <= 3) {
            return 'Easy';
        } elseif ($ingredients <= 10 && $instructions <= 6) {
            return 'Medium';
        } else {
            return 'Hard';
        }
    }

    /**
     * Estimate cost level.
     */
    private function estimateCost(array $recipe): string
    {
        $ingredients = $recipe['ingredients'] ?? [];
        $expensiveIngredients = 0;
        
        foreach ($ingredients as $ingredient) {
            $food = strtolower($ingredient['food'] ?? '');
            $expensiveKeywords = ['truffle', 'caviar', 'lobster', 'crab', 'salmon', 'beef', 'lamb', 'veal'];
            
            foreach ($expensiveKeywords as $keyword) {
                if (strpos($food, $keyword) !== false) {
                    $expensiveIngredients++;
                    break;
                }
            }
        }
        
        $totalIngredients = count($ingredients);
        $expensiveRatio = $totalIngredients > 0 ? $expensiveIngredients / $totalIngredients : 0;
        
        if ($expensiveRatio > 0.3) {
            return 'High';
        } elseif ($expensiveRatio > 0.1) {
            return 'Medium';
        } else {
            return 'Low';
        }
    }

    /**
     * Generate nutrition summary.
     */
    private function generateNutritionSummary(array $recipe): array
    {
        $nutrients = $recipe['totalNutrients'] ?? [];
        $calories = $recipe['calories'] ?? 0;
        $yield = $recipe['yield'] ?? 1;
        
        return [
            'caloriesPerServing' => round($yield > 0 ? $calories / $yield : $calories, 0),
            'macronutrients' => [
                'protein' => round(($nutrients['PROCNT']['quantity'] ?? 0) / $yield, 1),
                'carbs' => round(($nutrients['CHOCDF']['quantity'] ?? 0) / $yield, 1),
                'fat' => round(($nutrients['FAT']['quantity'] ?? 0) / $yield, 1)
            ],
            'fiber' => round(($nutrients['FIBTG']['quantity'] ?? 0) / $yield, 1),
            'sodium' => round(($nutrients['NA']['quantity'] ?? 0) / $yield, 1),
            'sugar' => round(($nutrients['SUGAR']['quantity'] ?? 0) / $yield, 1)
        ];
    }

    /**
     * Generate serving information.
     */
    private function generateServingInfo(array $recipe): array
    {
        $yield = $recipe['yield'] ?? 1;
        $totalWeight = $recipe['totalWeight'] ?? 0;
        
        return [
            'servings' => $yield,
            'weightPerServing' => round($yield > 0 ? $totalWeight / $yield : $totalWeight, 1),
            'totalWeight' => round($totalWeight, 1)
        ];
    }

    /**
     * Generate preparation information.
     */
    private function generatePreparationInfo(array $recipe): array
    {
        return [
            'ingredientCount' => count($recipe['ingredients'] ?? []),
            'instructionSteps' => count($recipe['instructions'] ?? []),
            'cuisineTypes' => $recipe['cuisineType'] ?? [],
            'mealTypes' => $recipe['mealType'] ?? [],
            'dishTypes' => $recipe['dishType'] ?? []
        ];
    }

    /**
     * Check if ingredient is a main ingredient.
     */
    private function isMainIngredient(array $ingredient): bool
    {
        $weight = $ingredient['weight'] ?? 0;
        return $weight > 100; // Consider ingredients over 100g as main ingredients
    }

    /**
     * Extract allergens from ingredient.
     */
    private function extractIngredientAllergens(array $ingredient): array
    {
        $allergens = [];
        $food = strtolower($ingredient['food'] ?? '');
        
        $allergenMap = [
            'milk' => ['milk', 'cheese', 'butter', 'cream', 'dairy'],
            'eggs' => ['egg'],
            'fish' => ['fish', 'salmon', 'tuna', 'cod'],
            'shellfish' => ['shrimp', 'crab', 'lobster'],
            'tree_nuts' => ['almond', 'walnut', 'pecan', 'cashew'],
            'peanuts' => ['peanut'],
            'wheat' => ['wheat', 'flour'],
            'soy' => ['soy', 'tofu']
        ];
        
        foreach ($allergenMap as $allergen => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($food, $keyword) !== false) {
                    $allergens[] = $allergen;
                    break;
                }
            }
        }
        
        return array_unique($allergens);
    }
}