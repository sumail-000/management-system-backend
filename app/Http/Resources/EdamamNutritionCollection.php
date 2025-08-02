<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EdamamNutritionCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = EdamamNutritionResource::class;

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
                'analyzedAt' => now()->toISOString(),
                'source' => 'Edamam Nutrition Analysis API',
                'version' => '2.0',
                'summary' => $this->generateCollectionSummary()
            ],
            'aggregated' => $this->generateAggregatedData()
        ];
    }

    /**
     * Generate summary for the collection.
     */
    private function generateCollectionSummary(): array
    {
        $totalCalories = 0;
        $totalWeight = 0;
        $allDietLabels = [];
        $allHealthLabels = [];
        $allCautions = [];
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            $totalCalories += $resource['calories'] ?? 0;
            $totalWeight += $resource['totalWeight'] ?? 0;
            
            $allDietLabels = array_merge($allDietLabels, $resource['dietLabels'] ?? []);
            $allHealthLabels = array_merge($allHealthLabels, $resource['healthLabels'] ?? []);
            $allCautions = array_merge($allCautions, $resource['cautions'] ?? []);
        }
        
        return [
            'totalItems' => $this->collection->count(),
            'totalCalories' => round($totalCalories, 0),
            'totalWeight' => round($totalWeight, 1),
            'averageCaloriesPerItem' => $this->collection->count() > 0 ? round($totalCalories / $this->collection->count(), 0) : 0,
            'averageWeightPerItem' => $this->collection->count() > 0 ? round($totalWeight / $this->collection->count(), 1) : 0,
            'commonDietLabels' => array_unique($allDietLabels),
            'commonHealthLabels' => array_unique($allHealthLabels),
            'allCautions' => array_unique($allCautions)
        ];
    }

    /**
     * Generate aggregated nutritional data.
     */
    private function generateAggregatedData(): array
    {
        $aggregatedNutrients = [];
        $aggregatedDaily = [];
        $totalCalories = 0;
        $totalWeight = 0;
        
        foreach ($this->collection as $item) {
            $resource = $item->resource;
            $totalCalories += $resource['calories'] ?? 0;
            $totalWeight += $resource['totalWeight'] ?? 0;
            
            // Aggregate nutrients
            $nutrients = $resource['totalNutrients'] ?? [];
            foreach ($nutrients as $key => $nutrient) {
                if (!isset($aggregatedNutrients[$key])) {
                    $aggregatedNutrients[$key] = [
                        'label' => $nutrient['label'] ?? $key,
                        'quantity' => 0,
                        'unit' => $nutrient['unit'] ?? ''
                    ];
                }
                $aggregatedNutrients[$key]['quantity'] += $nutrient['quantity'] ?? 0;
            }
            
            // Aggregate daily values
            $daily = $resource['totalDaily'] ?? [];
            foreach ($daily as $key => $dailyValue) {
                if (!isset($aggregatedDaily[$key])) {
                    $aggregatedDaily[$key] = [
                        'label' => $dailyValue['label'] ?? $key,
                        'quantity' => 0,
                        'unit' => $dailyValue['unit'] ?? '%'
                    ];
                }
                $aggregatedDaily[$key]['quantity'] += $dailyValue['quantity'] ?? 0;
            }
        }
        
        // Round aggregated values
        foreach ($aggregatedNutrients as $key => $nutrient) {
            $aggregatedNutrients[$key]['quantity'] = round($nutrient['quantity'], 2);
        }
        
        foreach ($aggregatedDaily as $key => $daily) {
            $aggregatedDaily[$key]['quantity'] = round($daily['quantity'], 1);
        }
        
        return [
            'totalNutrients' => $aggregatedNutrients,
            'totalDaily' => $aggregatedDaily,
            'totalCalories' => round($totalCalories, 0),
            'totalWeight' => round($totalWeight, 1),
            'macronutrientBreakdown' => $this->calculateMacronutrientBreakdown($aggregatedNutrients, $totalCalories),
            'nutritionGrade' => $this->calculateOverallNutritionGrade($aggregatedNutrients, $totalCalories),
            'healthScore' => $this->calculateHealthScore($aggregatedNutrients, $totalCalories),
            'recommendations' => $this->generateRecommendations($aggregatedNutrients, $aggregatedDaily)
        ];
    }

    /**
     * Calculate macronutrient breakdown.
     */
    private function calculateMacronutrientBreakdown(array $nutrients, float $totalCalories): array
    {
        $protein = $nutrients['PROCNT']['quantity'] ?? 0;
        $carbs = $nutrients['CHOCDF']['quantity'] ?? 0;
        $fat = $nutrients['FAT']['quantity'] ?? 0;
        
        $proteinCalories = $protein * 4;
        $carbCalories = $carbs * 4;
        $fatCalories = $fat * 9;
        
        return [
            'protein' => [
                'grams' => round($protein, 1),
                'calories' => round($proteinCalories, 0),
                'percentage' => $totalCalories > 0 ? round(($proteinCalories / $totalCalories) * 100, 1) : 0
            ],
            'carbohydrates' => [
                'grams' => round($carbs, 1),
                'calories' => round($carbCalories, 0),
                'percentage' => $totalCalories > 0 ? round(($carbCalories / $totalCalories) * 100, 1) : 0
            ],
            'fat' => [
                'grams' => round($fat, 1),
                'calories' => round($fatCalories, 0),
                'percentage' => $totalCalories > 0 ? round(($fatCalories / $totalCalories) * 100, 1) : 0
            ]
        ];
    }

    /**
     * Calculate overall nutrition grade.
     */
    private function calculateOverallNutritionGrade(array $nutrients, float $totalCalories): string
    {
        $score = 0;
        
        // Positive points
        $fiber = $nutrients['FIBTG']['quantity'] ?? 0;
        $protein = $nutrients['PROCNT']['quantity'] ?? 0;
        $vitaminC = $nutrients['VITC']['quantity'] ?? 0;
        $calcium = $nutrients['CA']['quantity'] ?? 0;
        $iron = $nutrients['FE']['quantity'] ?? 0;
        
        if ($fiber > 25) $score += 3;
        elseif ($fiber > 15) $score += 2;
        elseif ($fiber > 10) $score += 1;
        
        if ($protein > 50) $score += 2;
        elseif ($protein > 30) $score += 1;
        
        if ($vitaminC > 60) $score += 1;
        if ($calcium > 800) $score += 1;
        if ($iron > 10) $score += 1;
        
        // Negative points
        $sodium = $nutrients['NA']['quantity'] ?? 0;
        $sugar = $nutrients['SUGAR']['quantity'] ?? 0;
        $saturatedFat = $nutrients['FASAT']['quantity'] ?? 0;
        $cholesterol = $nutrients['CHOLE']['quantity'] ?? 0;
        
        if ($sodium > 2300) $score -= 3;
        elseif ($sodium > 1500) $score -= 2;
        elseif ($sodium > 1000) $score -= 1;
        
        if ($sugar > 50) $score -= 2;
        elseif ($sugar > 25) $score -= 1;
        
        if ($saturatedFat > 20) $score -= 2;
        elseif ($saturatedFat > 13) $score -= 1;
        
        if ($cholesterol > 300) $score -= 1;
        
        // Convert to grade
        if ($score >= 5) return 'A';
        if ($score >= 3) return 'B';
        if ($score >= 0) return 'C';
        if ($score >= -2) return 'D';
        
        return 'F';
    }

    /**
     * Calculate health score (0-100).
     */
    private function calculateHealthScore(array $nutrients, float $totalCalories): int
    {
        $score = 50; // Base score
        
        // Fiber bonus
        $fiber = $nutrients['FIBTG']['quantity'] ?? 0;
        $score += min(20, $fiber * 0.8);
        
        // Protein bonus
        $protein = $nutrients['PROCNT']['quantity'] ?? 0;
        $score += min(15, $protein * 0.3);
        
        // Vitamin and mineral bonuses
        $vitaminC = $nutrients['VITC']['quantity'] ?? 0;
        $calcium = $nutrients['CA']['quantity'] ?? 0;
        $iron = $nutrients['FE']['quantity'] ?? 0;
        
        $score += min(5, $vitaminC * 0.08);
        $score += min(5, $calcium * 0.006);
        $score += min(5, $iron * 0.5);
        
        // Penalties
        $sodium = $nutrients['NA']['quantity'] ?? 0;
        $sugar = $nutrients['SUGAR']['quantity'] ?? 0;
        $saturatedFat = $nutrients['FASAT']['quantity'] ?? 0;
        
        $score -= min(25, $sodium * 0.01);
        $score -= min(20, $sugar * 0.4);
        $score -= min(15, $saturatedFat * 1.2);
        
        return max(0, min(100, round($score)));
    }

    /**
     * Generate nutritional recommendations.
     */
    private function generateRecommendations(array $nutrients, array $daily): array
    {
        $recommendations = [];
        
        // Check fiber
        $fiberDaily = $daily['FIBTG']['quantity'] ?? 0;
        if ($fiberDaily < 50) {
            $recommendations[] = [
                'type' => 'increase',
                'nutrient' => 'fiber',
                'message' => 'Consider adding more fiber-rich foods like fruits, vegetables, and whole grains.',
                'priority' => 'medium'
            ];
        }
        
        // Check sodium
        $sodiumDaily = $daily['NA']['quantity'] ?? 0;
        if ($sodiumDaily > 100) {
            $recommendations[] = [
                'type' => 'reduce',
                'nutrient' => 'sodium',
                'message' => 'Try to reduce sodium intake by choosing fresh foods over processed ones.',
                'priority' => 'high'
            ];
        }
        
        // Check saturated fat
        $satFatDaily = $daily['FASAT']['quantity'] ?? 0;
        if ($satFatDaily > 100) {
            $recommendations[] = [
                'type' => 'reduce',
                'nutrient' => 'saturated_fat',
                'message' => 'Consider reducing saturated fat by choosing lean proteins and healthy fats.',
                'priority' => 'medium'
            ];
        }
        
        // Check protein
        $proteinDaily = $daily['PROCNT']['quantity'] ?? 0;
        if ($proteinDaily < 80) {
            $recommendations[] = [
                'type' => 'increase',
                'nutrient' => 'protein',
                'message' => 'Consider adding more protein sources like lean meats, legumes, or dairy.',
                'priority' => 'low'
            ];
        }
        
        // Check vitamins
        $vitaminCDaily = $daily['VITC']['quantity'] ?? 0;
        if ($vitaminCDaily < 80) {
            $recommendations[] = [
                'type' => 'increase',
                'nutrient' => 'vitamin_c',
                'message' => 'Add more vitamin C-rich foods like citrus fruits, berries, and bell peppers.',
                'priority' => 'low'
            ];
        }
        
        return $recommendations;
    }
}