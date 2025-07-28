<?php

namespace App\Services;

class NutritionDataTransformationService
{
    /**
     * Transform Edamam API response to match frontend data structure
     * This mirrors the frontend's data transformation logic in NutritionAnalysis.tsx
     */
    public function transformEdamamResponse(array $edamamResponse): array
    {
        $totalNutrients = $edamamResponse['totalNutrients'] ?? [];
        $totalDaily = $edamamResponse['totalDaily'] ?? [];
        $healthLabels = $edamamResponse['healthLabels'] ?? [];
        $cautions = $edamamResponse['cautions'] ?? [];
        $warnings = $edamamResponse['warnings'] ?? [];
        $dietLabels = $edamamResponse['dietLabels'] ?? [];
        $totalWeight = $edamamResponse['totalWeight'] ?? 0;
        $yield = $edamamResponse['yield'] ?? 1;

        // Calculate total calories
        $totalCalories = $totalNutrients['ENERC_KCAL']['quantity'] ?? 0;

        // Transform macronutrients
        $macros = [
            'carbs' => [
                'amount' => round($totalNutrients['CHOCDF']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['CHOCDF']['unit'] ?? 'g',
                'dailyValue' => round($totalDaily['CHOCDF']['quantity'] ?? 0, 1)
            ],
            'protein' => [
                'amount' => round($totalNutrients['PROCNT']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['PROCNT']['unit'] ?? 'g',
                'dailyValue' => round($totalDaily['PROCNT']['quantity'] ?? 0, 1)
            ],
            'fat' => [
                'amount' => round($totalNutrients['FAT']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['FAT']['unit'] ?? 'g',
                'dailyValue' => round($totalDaily['FAT']['quantity'] ?? 0, 1)
            ],
            'saturatedFat' => [
                'amount' => round($totalNutrients['FASAT']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['FASAT']['unit'] ?? 'g',
                'dailyValue' => round($totalDaily['FASAT']['quantity'] ?? 0, 1)
            ],
            'transFat' => [
                'amount' => round($totalNutrients['FATRN']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['FATRN']['unit'] ?? 'g',
                'dailyValue' => null
            ],
            'fiber' => [
                'amount' => round($totalNutrients['FIBTG']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['FIBTG']['unit'] ?? 'g',
                'dailyValue' => round($totalDaily['FIBTG']['quantity'] ?? 0, 1)
            ],
            'sugar' => [
                'amount' => round($totalNutrients['SUGAR']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['SUGAR']['unit'] ?? 'g',
                'dailyValue' => null
            ],
            'sodium' => [
                'amount' => round($totalNutrients['NA']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['NA']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['NA']['quantity'] ?? 0, 1)
            ],
            'cholesterol' => [
                'amount' => round($totalNutrients['CHOLE']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['CHOLE']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['CHOLE']['quantity'] ?? 0, 1)
            ]
        ];

        // Transform micronutrients (vitamins and minerals)
        $micros = [
            'vitaminA' => [
                'amount' => round($totalNutrients['VITA_RAE']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['VITA_RAE']['unit'] ?? 'µg',
                'dailyValue' => round($totalDaily['VITA_RAE']['quantity'] ?? 0, 1)
            ],
            'vitaminC' => [
                'amount' => round($totalNutrients['VITC']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['VITC']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['VITC']['quantity'] ?? 0, 1)
            ],
            'vitaminD' => [
                'amount' => round($totalNutrients['VITD']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['VITD']['unit'] ?? 'µg',
                'dailyValue' => round($totalDaily['VITD']['quantity'] ?? 0, 1)
            ],
            'vitaminE' => [
                'amount' => round($totalNutrients['TOCPHA']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['TOCPHA']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['TOCPHA']['quantity'] ?? 0, 1)
            ],
            'vitaminK' => [
                'amount' => round($totalNutrients['VITK1']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['VITK1']['unit'] ?? 'µg',
                'dailyValue' => round($totalDaily['VITK1']['quantity'] ?? 0, 1)
            ],
            'thiamin' => [
                'amount' => round($totalNutrients['THIA']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['THIA']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['THIA']['quantity'] ?? 0, 1)
            ],
            'riboflavin' => [
                'amount' => round($totalNutrients['RIBF']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['RIBF']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['RIBF']['quantity'] ?? 0, 1)
            ],
            'niacin' => [
                'amount' => round($totalNutrients['NIA']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['NIA']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['NIA']['quantity'] ?? 0, 1)
            ],
            'vitaminB6' => [
                'amount' => round($totalNutrients['VITB6A']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['VITB6A']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['VITB6A']['quantity'] ?? 0, 1)
            ],
            'folate' => [
                'amount' => round($totalNutrients['FOLDFE']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['FOLDFE']['unit'] ?? 'µg',
                'dailyValue' => round($totalDaily['FOLDFE']['quantity'] ?? 0, 1)
            ],
            'vitaminB12' => [
                'amount' => round($totalNutrients['VITB12']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['VITB12']['unit'] ?? 'µg',
                'dailyValue' => round($totalDaily['VITB12']['quantity'] ?? 0, 1)
            ],
            'calcium' => [
                'amount' => round($totalNutrients['CA']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['CA']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['CA']['quantity'] ?? 0, 1)
            ],
            'iron' => [
                'amount' => round($totalNutrients['FE']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['FE']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['FE']['quantity'] ?? 0, 1)
            ],
            'magnesium' => [
                'amount' => round($totalNutrients['MG']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['MG']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['MG']['quantity'] ?? 0, 1)
            ],
            'phosphorus' => [
                'amount' => round($totalNutrients['P']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['P']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['P']['quantity'] ?? 0, 1)
            ],
            'potassium' => [
                'amount' => round($totalNutrients['K']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['K']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['K']['quantity'] ?? 0, 1)
            ],
            'zinc' => [
                'amount' => round($totalNutrients['ZN']['quantity'] ?? 0, 2),
                'unit' => $totalNutrients['ZN']['unit'] ?? 'mg',
                'dailyValue' => round($totalDaily['ZN']['quantity'] ?? 0, 1)
            ]
        ];

        // Process allergens and warnings
        $allergens = $this->extractAllergens($healthLabels);
        $processedWarnings = array_merge($cautions, $warnings);

        // Calculate servings and weight per serving
        $servings = $yield > 0 ? $yield : 1;
        $weightPerServing = $servings > 0 ? round($totalWeight / $servings, 2) : 0;

        return [
            'totalCalories' => round($totalCalories, 2),
            'macros' => $macros,
            'micros' => $micros,
            'allergens' => $allergens,
            'warnings' => $processedWarnings,
            'servings' => $servings,
            'weightPerServing' => $weightPerServing,
            'healthLabels' => $healthLabels,
            'dietLabels' => $dietLabels
        ];
    }

    /**
     * Extract allergen information from health labels
     */
    private function extractAllergens(array $healthLabels): array
    {
        $allergenMap = [
            'DAIRY_FREE' => 'Contains Dairy',
            'EGG_FREE' => 'Contains Eggs',
            'FISH_FREE' => 'Contains Fish',
            'GLUTEN_FREE' => 'Contains Gluten',
            'PEANUT_FREE' => 'Contains Peanuts',
            'SESAME_FREE' => 'Contains Sesame',
            'SHELLFISH_FREE' => 'Contains Shellfish',
            'SOY_FREE' => 'Contains Soy',
            'TREE_NUT_FREE' => 'Contains Tree Nuts',
            'WHEAT_FREE' => 'Contains Wheat'
        ];

        $allergens = [];
        foreach ($allergenMap as $freeLabel => $allergenText) {
            if (!in_array($freeLabel, $healthLabels)) {
                $allergens[] = $allergenText;
            }
        }

        return $allergens;
    }

    /**
     * Prepare data for database storage
     * Maps the transformed data to database column names
     */
    public function prepareForDatabase(array $transformedData, int $productId, array $rawEdamamResponse): array
    {
        return [
            'product_id' => $productId,
            'calories' => $transformedData['totalCalories'],
            'total_fat' => $transformedData['macros']['fat']['amount'],
            'saturated_fat' => $transformedData['macros']['saturatedFat']['amount'],
            'trans_fat' => $transformedData['macros']['transFat']['amount'],
            'cholesterol' => $transformedData['macros']['cholesterol']['amount'],
            'sodium' => $transformedData['macros']['sodium']['amount'],
            'total_carbohydrate' => $transformedData['macros']['carbs']['amount'],
            'dietary_fiber' => $transformedData['macros']['fiber']['amount'],
            'sugars' => $transformedData['macros']['sugar']['amount'],
            'protein' => $transformedData['macros']['protein']['amount'],
            'vitamin_a' => $transformedData['micros']['vitaminA']['amount'],
            'vitamin_c' => $transformedData['micros']['vitaminC']['amount'],
            'calcium' => $transformedData['micros']['calcium']['amount'],
            'iron' => $transformedData['micros']['iron']['amount'],
            'potassium' => $transformedData['micros']['potassium']['amount'],
            'edamam_response' => json_encode($rawEdamamResponse)
        ];
    }
}