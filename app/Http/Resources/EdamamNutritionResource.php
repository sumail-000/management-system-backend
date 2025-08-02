<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EdamamNutritionResource extends JsonResource
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
            'yield' => $this->resource['yield'] ?? null,
            'calories' => $this->resource['calories'] ?? 0,
            'totalWeight' => $this->resource['totalWeight'] ?? 0,
            'dietLabels' => $this->resource['dietLabels'] ?? [],
            'healthLabels' => $this->resource['healthLabels'] ?? [],
            'cautions' => $this->resource['cautions'] ?? [],
            'totalNutrients' => $this->formatNutrients($this->resource['totalNutrients'] ?? []),
            'totalDaily' => $this->formatNutrients($this->resource['totalDaily'] ?? []),
            'ingredients' => $this->formatIngredients($this->resource['ingredients'] ?? []),
            'totalNutrientsKCal' => $this->formatNutrients($this->resource['totalNutrientsKCal'] ?? []),
            'co2EmissionsClass' => $this->resource['co2EmissionsClass'] ?? null,
            'totalCO2Emissions' => $this->resource['totalCO2Emissions'] ?? null,
            'highNutrients' => $this->identifyHighNutrients(),
            'nutritionSummary' => $this->generateNutritionSummary(),
            'analysisMetadata' => [
                'analyzedAt' => now()->toISOString(),
                'source' => 'Edamam Nutrition Analysis API',
                'version' => '2.0'
            ]
        ];
    }

    /**
     * Format nutrients data for consistent structure.
     */
    private function formatNutrients(array $nutrients): array
    {
        $formatted = [];
        
        foreach ($nutrients as $key => $nutrient) {
            $formatted[$key] = [
                'label' => $nutrient['label'] ?? $key,
                'quantity' => round($nutrient['quantity'] ?? 0, 2),
                'unit' => $nutrient['unit'] ?? '',
                'percentage' => isset($nutrient['quantity']) && isset($nutrient['unit']) 
                    ? $this->calculatePercentage($key, $nutrient['quantity']) 
                    : null
            ];
        }
        
        return $formatted;
    }

    /**
     * Format ingredients data.
     */
    private function formatIngredients(array $ingredients): array
    {
        return array_map(function ($ingredient) {
            return [
                'text' => $ingredient['text'] ?? '',
                'parsed' => array_map(function ($parsed) {
                    return [
                        'quantity' => $parsed['quantity'] ?? null,
                        'measure' => $parsed['measure'] ?? null,
                        'food' => $parsed['food'] ?? '',
                        'foodId' => $parsed['foodId'] ?? null,
                        'weight' => $parsed['weight'] ?? 0,
                        'retainedWeight' => $parsed['retainedWeight'] ?? 0,
                        'nutrients' => $this->formatNutrients($parsed['nutrients'] ?? []),
                        'measureURI' => $parsed['measureURI'] ?? null,
                        'status' => $parsed['status'] ?? 'OK'
                    ];
                }, $ingredient['parsed'] ?? [])
            ];
        }, $ingredients);
    }

    /**
     * Identify nutrients that are particularly high.
     */
    private function identifyHighNutrients(): array
    {
        $totalDaily = $this->resource['totalDaily'] ?? [];
        $highNutrients = [];
        
        foreach ($totalDaily as $key => $nutrient) {
            $percentage = $nutrient['quantity'] ?? 0;
            
            if ($percentage > 20) { // More than 20% daily value
                $highNutrients[] = [
                    'nutrient' => $key,
                    'label' => $nutrient['label'] ?? $key,
                    'percentage' => round($percentage, 1),
                    'level' => $this->getNutrientLevel($percentage)
                ];
            }
        }
        
        // Sort by percentage descending
        usort($highNutrients, function ($a, $b) {
            return $b['percentage'] <=> $a['percentage'];
        });
        
        return $highNutrients;
    }

    /**
     * Generate a nutrition summary.
     */
    private function generateNutritionSummary(): array
    {
        $totalNutrients = $this->resource['totalNutrients'] ?? [];
        $calories = $this->resource['calories'] ?? 0;
        $weight = $this->resource['totalWeight'] ?? 0;
        
        return [
            'calories' => round($calories, 0),
            'caloriesPerGram' => $weight > 0 ? round($calories / $weight, 2) : 0,
            'macronutrients' => [
                'protein' => [
                    'grams' => round($totalNutrients['PROCNT']['quantity'] ?? 0, 1),
                    'calories' => round(($totalNutrients['PROCNT']['quantity'] ?? 0) * 4, 0),
                    'percentage' => $calories > 0 ? round((($totalNutrients['PROCNT']['quantity'] ?? 0) * 4 / $calories) * 100, 1) : 0
                ],
                'carbs' => [
                    'grams' => round($totalNutrients['CHOCDF']['quantity'] ?? 0, 1),
                    'calories' => round(($totalNutrients['CHOCDF']['quantity'] ?? 0) * 4, 0),
                    'percentage' => $calories > 0 ? round((($totalNutrients['CHOCDF']['quantity'] ?? 0) * 4 / $calories) * 100, 1) : 0
                ],
                'fat' => [
                    'grams' => round($totalNutrients['FAT']['quantity'] ?? 0, 1),
                    'calories' => round(($totalNutrients['FAT']['quantity'] ?? 0) * 9, 0),
                    'percentage' => $calories > 0 ? round((($totalNutrients['FAT']['quantity'] ?? 0) * 9 / $calories) * 100, 1) : 0
                ]
            ],
            'fiber' => round($totalNutrients['FIBTG']['quantity'] ?? 0, 1),
            'sodium' => round($totalNutrients['NA']['quantity'] ?? 0, 1),
            'sugar' => round($totalNutrients['SUGAR']['quantity'] ?? 0, 1)
        ];
    }

    /**
     * Calculate percentage for daily value.
     */
    private function calculatePercentage(string $nutrientKey, float $quantity): ?float
    {
        // Daily value references (simplified)
        $dailyValues = [
            'PROCNT' => 50, // Protein (g)
            'FAT' => 65,    // Fat (g)
            'CHOCDF' => 300, // Carbs (g)
            'FIBTG' => 25,  // Fiber (g)
            'NA' => 2300,   // Sodium (mg)
            'SUGAR' => 50   // Sugar (g)
        ];
        
        if (isset($dailyValues[$nutrientKey])) {
            return round(($quantity / $dailyValues[$nutrientKey]) * 100, 1);
        }
        
        return null;
    }

    /**
     * Get nutrient level based on percentage.
     */
    private function getNutrientLevel(float $percentage): string
    {
        if ($percentage >= 50) {
            return 'very_high';
        } elseif ($percentage >= 30) {
            return 'high';
        } elseif ($percentage >= 20) {
            return 'moderate';
        }
        
        return 'normal';
    }
}