<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormattedRecipeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uri' => $this->resource['uri'] ?? null,
            'label' => $this->resource['label'] ?? '',
            'image' => $this->resource['image'] ?? null,
            'images' => $this->resource['images'] ?? [],
            'source' => $this->resource['source'] ?? null,
            'url' => $this->resource['url'] ?? null,
            'shareAs' => $this->resource['shareAs'] ?? null,
            'yield' => $this->resource['yield'] ?? 0,
            'dietLabels' => $this->resource['dietLabels'] ?? [],
            'healthLabels' => $this->resource['healthLabels'] ?? [],
            'cautions' => $this->resource['cautions'] ?? [],
            'ingredientLines' => $this->resource['ingredientLines'] ?? [],
            'ingredients' => $this->formatIngredients($this->resource['ingredients'] ?? []),
            'calories' => round($this->resource['calories'] ?? 0, 0),
            'totalCO2Emissions' => $this->resource['totalCO2Emissions'] ?? null,
            'co2EmissionsClass' => $this->resource['co2EmissionsClass'] ?? null,
            'totalTime' => $this->resource['totalTime'] ?? 0,
            'cuisineType' => $this->resource['cuisineType'] ?? [],
            'mealType' => $this->resource['mealType'] ?? [],
            'dishType' => $this->resource['dishType'] ?? [],
            'totalNutrients' => $this->formatNutrients($this->resource['totalNutrients'] ?? []),
            'totalDaily' => $this->formatNutrients($this->resource['totalDaily'] ?? []),
            'digest' => $this->formatDigest($this->resource['digest'] ?? []),
            
            // Additional computed fields
            'difficultyLevel' => $this->estimateDifficulty(),
            'costEstimate' => $this->estimateCost(),
            'nutritionScore' => $this->calculateNutritionScore(),
            'servingInfo' => $this->generateServingInfo(),
            'preparationInfo' => $this->generatePreparationInfo(),
            'tags' => $this->generateTags(),
            'allergens' => $this->extractAllergens(),
            
            // Metadata
            'metadata' => [
                'processedAt' => now()->toISOString(),
                'source' => 'Edamam Recipe API',
                'version' => '2.0'
            ]
        ];
    }

    /**
     * Format ingredients data.
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
                'isMainIngredient' => $this->isMainIngredient($ingredient)
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
     * Estimate recipe difficulty.
     */
    private function estimateDifficulty(): string
    {
        $ingredients = $this->resource['ingredients'] ?? [];
        $totalTime = $this->resource['totalTime'] ?? 0;
        $ingredientLines = $this->resource['ingredientLines'] ?? [];
        
        $score = 0;
        
        // Base on number of ingredients
        $ingredientCount = count($ingredientLines);
        if ($ingredientCount > 15) $score += 3;
        elseif ($ingredientCount > 10) $score += 2;
        elseif ($ingredientCount > 5) $score += 1;
        
        // Base on cooking time
        if ($totalTime > 120) $score += 3;
        elseif ($totalTime > 60) $score += 2;
        elseif ($totalTime > 30) $score += 1;
        
        // Base on cooking techniques (check ingredient lines for complex terms)
        $complexTerms = ['marinate', 'braise', 'confit', 'sous vide', 'flambé', 'julienne', 'brunoise'];
        foreach ($ingredientLines as $line) {
            foreach ($complexTerms as $term) {
                if (stripos($line, $term) !== false) {
                    $score += 1;
                    break;
                }
            }
        }
        
        if ($score >= 5) return 'hard';
        if ($score >= 3) return 'medium';
        return 'easy';
    }

    /**
     * Estimate recipe cost.
     */
    private function estimateCost(): string
    {
        $ingredients = $this->resource['ingredients'] ?? [];
        $expensiveIngredients = ['truffle', 'caviar', 'lobster', 'crab', 'salmon', 'beef', 'lamb', 'veal'];
        $moderateIngredients = ['chicken', 'pork', 'fish', 'cheese', 'nuts', 'wine'];
        
        $score = 0;
        $ingredientText = strtolower(implode(' ', $this->resource['ingredientLines'] ?? []));
        
        foreach ($expensiveIngredients as $ingredient) {
            if (strpos($ingredientText, $ingredient) !== false) {
                $score += 3;
            }
        }
        
        foreach ($moderateIngredients as $ingredient) {
            if (strpos($ingredientText, $ingredient) !== false) {
                $score += 1;
            }
        }
        
        if ($score >= 6) return 'expensive';
        if ($score >= 3) return 'moderate';
        return 'budget';
    }

    /**
     * Calculate nutrition score.
     */
    private function calculateNutritionScore(): float
    {
        $score = 5.0; // Base score
        $calories = $this->resource['calories'] ?? 0;
        $nutrients = $this->resource['totalNutrients'] ?? [];
        
        // Adjust based on calorie density
        $yield = $this->resource['yield'] ?? 1;
        $caloriesPerServing = $yield > 0 ? $calories / $yield : $calories;
        
        if ($caloriesPerServing < 300) $score += 1;
        elseif ($caloriesPerServing > 600) $score -= 1;
        
        // Boost for fiber
        if (isset($nutrients['FIBTG']) && $nutrients['FIBTG']['quantity'] > 5) {
            $score += 0.5;
        }
        
        // Reduce for high sodium
        if (isset($nutrients['NA']) && $nutrients['NA']['quantity'] > 1000) {
            $score -= 0.5;
        }
        
        // Boost for protein
        if (isset($nutrients['PROCNT']) && $nutrients['PROCNT']['quantity'] > 20) {
            $score += 0.5;
        }
        
        return round(max(1.0, min(10.0, $score)), 1);
    }

    /**
     * Generate serving information.
     */
    private function generateServingInfo(): array
    {
        $yield = $this->resource['yield'] ?? 1;
        $calories = $this->resource['calories'] ?? 0;
        
        return [
            'servings' => $yield,
            'caloriesPerServing' => $yield > 0 ? round($calories / $yield, 0) : $calories,
            'portionSize' => $this->estimatePortionSize(),
            'servingType' => $this->determineServingType()
        ];
    }

    /**
     * Generate preparation information.
     */
    private function generatePreparationInfo(): array
    {
        $totalTime = $this->resource['totalTime'] ?? 0;
        
        return [
            'totalTime' => $totalTime,
            'estimatedPrepTime' => round($totalTime * 0.3, 0), // Estimate 30% for prep
            'estimatedCookTime' => round($totalTime * 0.7, 0), // Estimate 70% for cooking
            'timeCategory' => $this->categorizeTime($totalTime),
            'skillLevel' => $this->estimateDifficulty()
        ];
    }

    /**
     * Generate tags for the recipe.
     */
    private function generateTags(): array
    {
        $tags = [];
        
        // Add diet and health labels
        $tags = array_merge($tags, $this->resource['dietLabels'] ?? []);
        $tags = array_merge($tags, array_slice($this->resource['healthLabels'] ?? [], 0, 5));
        
        // Add meal and dish types
        $tags = array_merge($tags, $this->resource['mealType'] ?? []);
        $tags = array_merge($tags, $this->resource['dishType'] ?? []);
        
        // Add cuisine type
        $tags = array_merge($tags, $this->resource['cuisineType'] ?? []);
        
        // Add time-based tags
        $totalTime = $this->resource['totalTime'] ?? 0;
        if ($totalTime <= 30) $tags[] = 'Quick';
        if ($totalTime <= 15) $tags[] = 'Express';
        
        // Add calorie-based tags
        $yield = $this->resource['yield'] ?? 1;
        $caloriesPerServing = $yield > 0 ? ($this->resource['calories'] ?? 0) / $yield : 0;
        if ($caloriesPerServing < 300) $tags[] = 'Light';
        if ($caloriesPerServing > 600) $tags[] = 'Hearty';
        
        return array_values(array_unique(array_filter($tags)));
    }

    /**
     * Extract allergens from the recipe.
     */
    private function extractAllergens(): array
    {
        $allergens = [];
        $ingredientText = strtolower(implode(' ', $this->resource['ingredientLines'] ?? []));
        
        $allergenMap = [
            'dairy' => ['milk', 'cheese', 'butter', 'cream', 'yogurt'],
            'eggs' => ['egg', 'eggs'],
            'nuts' => ['almond', 'walnut', 'pecan', 'cashew', 'pistachio', 'hazelnut'],
            'soy' => ['soy', 'tofu', 'tempeh'],
            'gluten' => ['wheat', 'flour', 'bread', 'pasta'],
            'shellfish' => ['shrimp', 'crab', 'lobster', 'scallop'],
            'fish' => ['salmon', 'tuna', 'cod', 'fish']
        ];
        
        foreach ($allergenMap as $allergen => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($ingredientText, $keyword) !== false) {
                    $allergens[] = $allergen;
                    break;
                }
            }
        }
        
        return array_unique($allergens);
    }

    /**
     * Check if ingredient is a main ingredient.
     */
    private function isMainIngredient(array $ingredient): bool
    {
        $weight = $ingredient['weight'] ?? 0;
        $foodCategory = $ingredient['foodCategory'] ?? '';
        
        // Consider ingredients with higher weight as main ingredients
        if ($weight > 100) return true;
        
        // Consider protein sources as main ingredients
        $proteinCategories = ['Poultry', 'Meat', 'Fish', 'Legumes'];
        if (in_array($foodCategory, $proteinCategories)) return true;
        
        return false;
    }

    /**
     * Estimate portion size.
     */
    private function estimatePortionSize(): string
    {
        $calories = $this->resource['calories'] ?? 0;
        $yield = $this->resource['yield'] ?? 1;
        $caloriesPerServing = $yield > 0 ? $calories / $yield : $calories;
        
        if ($caloriesPerServing < 200) return 'small';
        if ($caloriesPerServing > 500) return 'large';
        return 'medium';
    }

    /**
     * Determine serving type.
     */
    private function determineServingType(): string
    {
        $mealTypes = $this->resource['mealType'] ?? [];
        $dishTypes = $this->resource['dishType'] ?? [];
        
        if (in_array('snack', $mealTypes)) return 'snack';
        if (in_array('dessert', $dishTypes)) return 'dessert';
        if (in_array('appetizer', $dishTypes)) return 'appetizer';
        if (in_array('main course', $dishTypes)) return 'main';
        if (in_array('side dish', $dishTypes)) return 'side';
        
        return 'main';
    }

    /**
     * Categorize time.
     */
    private function categorizeTime(int $time): string
    {
        if ($time <= 15) return 'express';
        if ($time <= 30) return 'quick';
        if ($time <= 60) return 'moderate';
        if ($time <= 120) return 'long';
        return 'extended';
    }
}