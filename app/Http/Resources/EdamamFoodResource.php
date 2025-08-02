<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EdamamFoodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'text' => $this->resource['text'] ?? '',
            'parsed' => $this->formatParsedFoods($this->resource['parsed'] ?? []),
            'hints' => $this->formatHints($this->resource['hints'] ?? []),
            '_links' => $this->formatLinks($this->resource['_links'] ?? []),
            'searchMetadata' => [
                'searchedAt' => now()->toISOString(),
                'source' => 'Edamam Food Database API',
                'version' => '2.0',
                'totalResults' => count($this->resource['hints'] ?? [])
            ]
        ];
    }

    /**
     * Format parsed foods data.
     */
    private function formatParsedFoods(array $parsed): array
    {
        return array_map(function ($food) {
            return [
                'food' => $this->formatFoodItem($food['food'] ?? []),
                'quantity' => $food['quantity'] ?? null,
                'measure' => $this->formatMeasure($food['measure'] ?? null),
                'weight' => $food['weight'] ?? 0,
                'retainedWeight' => $food['retainedWeight'] ?? 0,
                'nutrients' => $this->formatNutrients($food['nutrients'] ?? []),
                'measureURI' => $food['measureURI'] ?? null,
                'status' => $food['status'] ?? 'OK'
            ];
        }, $parsed);
    }

    /**
     * Format hints (food suggestions) data.
     */
    private function formatHints(array $hints): array
    {
        return array_map(function ($hint) {
            return [
                'food' => $this->formatFoodItem($hint['food'] ?? []),
                'measures' => $this->formatMeasures($hint['measures'] ?? []),
                'relevanceScore' => $this->calculateRelevanceScore($hint),
                'category' => $this->determineFoodCategory($hint['food'] ?? []),
                'isGeneric' => $this->isGenericFood($hint['food'] ?? [])
            ];
        }, $hints);
    }

    /**
     * Format individual food item.
     */
    private function formatFoodItem(array $food): array
    {
        return [
            'foodId' => $food['foodId'] ?? null,
            'uri' => $food['uri'] ?? null,
            'label' => $food['label'] ?? '',
            'knownAs' => $food['knownAs'] ?? '',
            'nutrients' => $this->formatNutrients($food['nutrients'] ?? []),
            'category' => $food['category'] ?? null,
            'categoryLabel' => $food['categoryLabel'] ?? null,
            'image' => $food['image'] ?? null,
            'brand' => $food['brand'] ?? null,
            'foodContentsLabel' => $food['foodContentsLabel'] ?? null,
            'servingsPerContainer' => $food['servingsPerContainer'] ?? null,
            'upc' => $food['upc'] ?? null,
            'ean' => $food['ean'] ?? null,
            'isAlcoholic' => $this->checkIfAlcoholic($food),
            'allergens' => $this->extractAllergens($food),
            'nutritionGrade' => $this->calculateNutritionGrade($food['nutrients'] ?? [])
        ];
    }

    /**
     * Format measure data.
     */
    private function formatMeasure(?array $measure): ?array
    {
        if (!$measure) {
            return null;
        }

        return [
            'uri' => $measure['uri'] ?? null,
            'label' => $measure['label'] ?? '',
            'weight' => $measure['weight'] ?? 0,
            'qualified' => $measure['qualified'] ?? []
        ];
    }

    /**
     * Format measures array.
     */
    private function formatMeasures(array $measures): array
    {
        return array_map(function ($measure) {
            return $this->formatMeasure($measure);
        }, $measures);
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
     * Calculate relevance score for food item.
     */
    private function calculateRelevanceScore(array $hint): float
    {
        $score = 0.5; // Base score
        
        $food = $hint['food'] ?? [];
        
        // Boost score for branded items
        if (!empty($food['brand'])) {
            $score += 0.2;
        }
        
        // Boost score for items with images
        if (!empty($food['image'])) {
            $score += 0.1;
        }
        
        // Boost score for items with UPC/EAN
        if (!empty($food['upc']) || !empty($food['ean'])) {
            $score += 0.1;
        }
        
        // Boost score for items with detailed nutrition
        $nutrients = $food['nutrients'] ?? [];
        if (count($nutrients) > 10) {
            $score += 0.1;
        }
        
        return round(min($score, 1.0), 2);
    }

    /**
     * Determine food category.
     */
    private function determineFoodCategory(array $food): string
    {
        if (!empty($food['categoryLabel'])) {
            return $food['categoryLabel'];
        }
        
        if (!empty($food['category'])) {
            return $food['category'];
        }
        
        // Determine category based on food properties
        if (!empty($food['brand'])) {
            return 'Packaged Foods';
        }
        
        return 'Generic Foods';
    }

    /**
     * Check if food is generic (not branded).
     */
    private function isGenericFood(array $food): bool
    {
        return empty($food['brand']) && empty($food['upc']) && empty($food['ean']);
    }

    /**
     * Check if food contains alcohol.
     */
    private function checkIfAlcoholic(array $food): bool
    {
        $label = strtolower($food['label'] ?? '');
        $alcoholKeywords = ['beer', 'wine', 'vodka', 'whiskey', 'rum', 'gin', 'tequila', 'alcohol', 'liquor', 'cocktail'];
        
        foreach ($alcoholKeywords as $keyword) {
            if (strpos($label, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract potential allergens from food data.
     */
    private function extractAllergens(array $food): array
    {
        $allergens = [];
        $label = strtolower($food['label'] ?? '');
        $contents = strtolower($food['foodContentsLabel'] ?? '');
        $searchText = $label . ' ' . $contents;
        
        $allergenKeywords = [
            'milk' => ['milk', 'dairy', 'cheese', 'butter', 'cream'],
            'eggs' => ['egg', 'eggs'],
            'fish' => ['fish', 'salmon', 'tuna', 'cod'],
            'shellfish' => ['shrimp', 'crab', 'lobster', 'shellfish'],
            'tree_nuts' => ['almond', 'walnut', 'pecan', 'cashew', 'pistachio'],
            'peanuts' => ['peanut', 'peanuts'],
            'wheat' => ['wheat', 'flour'],
            'soy' => ['soy', 'soybean', 'tofu']
        ];
        
        foreach ($allergenKeywords as $allergen => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($searchText, $keyword) !== false) {
                    $allergens[] = $allergen;
                    break;
                }
            }
        }
        
        return array_unique($allergens);
    }

    /**
     * Calculate nutrition grade (A-F) based on nutrients.
     */
    private function calculateNutritionGrade(array $nutrients): string
    {
        $score = 0;
        
        // Positive points for beneficial nutrients
        $fiber = $nutrients['FIBTG']['quantity'] ?? 0;
        $protein = $nutrients['PROCNT']['quantity'] ?? 0;
        
        if ($fiber > 5) $score += 2;
        if ($protein > 10) $score += 2;
        
        // Negative points for less beneficial nutrients
        $sodium = $nutrients['NA']['quantity'] ?? 0;
        $sugar = $nutrients['SUGAR']['quantity'] ?? 0;
        $saturatedFat = $nutrients['FASAT']['quantity'] ?? 0;
        
        if ($sodium > 500) $score -= 1;
        if ($sugar > 10) $score -= 1;
        if ($saturatedFat > 5) $score -= 1;
        
        // Convert score to grade
        if ($score >= 3) return 'A';
        if ($score >= 1) return 'B';
        if ($score >= -1) return 'C';
        if ($score >= -3) return 'D';
        
        return 'F';
    }
}