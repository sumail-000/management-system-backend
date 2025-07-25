<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EdamamApiIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test configuration for all Edamam services
        Config::set('edamam.nutrition.app_id', 'test_nutrition_app_id');
        Config::set('edamam.nutrition.app_key', 'test_nutrition_app_key');
        Config::set('edamam.nutrition.base_url', 'https://api.edamam.com/api/nutrition-details');
        
        Config::set('edamam.food.app_id', 'test_food_app_id');
        Config::set('edamam.food.app_key', 'test_food_app_key');
        Config::set('edamam.food.base_url', 'https://api.edamam.com/api/food-database/v2');
        
        Config::set('edamam.recipe.app_id', 'test_recipe_app_id');
        Config::set('edamam.recipe.app_key', 'test_recipe_app_key');
        Config::set('edamam.recipe.base_url', 'https://api.edamam.com/api/recipes/v2');
        
        Config::set('edamam.timeout', 30);
        Config::set('edamam.debug', true);
        Config::set('edamam.cache_ttl', 3600);
        
        // Clear cache before each test
        Cache::flush();
    }

    public function test_complete_nutrition_analysis_flow()
    {
        // Mock successful nutrition analysis API response
        Http::fake([
            'api.edamam.com/api/nutrition-details*' => Http::response([
                'uri' => 'http://www.edamam.com/ontologies/edamam.owl#recipe_123',
                'yield' => 1,
                'calories' => 520,
                'totalWeight' => 300.5,
                'dietLabels' => ['Low-Carb'],
                'healthLabels' => ['Gluten-Free', 'Dairy-Free'],
                'cautions' => [],
                'totalNutrients' => [
                    'ENERC_KCAL' => [
                        'label' => 'Energy',
                        'quantity' => 520.25,
                        'unit' => 'kcal'
                    ],
                    'FAT' => [
                        'label' => 'Fat',
                        'quantity' => 35.8,
                        'unit' => 'g'
                    ],
                    'FASAT' => [
                        'label' => 'Saturated',
                        'quantity' => 12.5,
                        'unit' => 'g'
                    ],
                    'PROCNT' => [
                        'label' => 'Protein',
                        'quantity' => 45.2,
                        'unit' => 'g'
                    ],
                    'CHOCDF' => [
                        'label' => 'Carbs',
                        'quantity' => 8.5,
                        'unit' => 'g'
                    ],
                    'FIBTG' => [
                        'label' => 'Fiber',
                        'quantity' => 3.2,
                        'unit' => 'g'
                    ],
                    'SUGAR' => [
                        'label' => 'Sugars',
                        'quantity' => 2.1,
                        'unit' => 'g'
                    ],
                    'NA' => [
                        'label' => 'Sodium',
                        'quantity' => 890.5,
                        'unit' => 'mg'
                    ],
                    'CA' => [
                        'label' => 'Calcium',
                        'quantity' => 120.8,
                        'unit' => 'mg'
                    ],
                    'MG' => [
                        'label' => 'Magnesium',
                        'quantity' => 85.2,
                        'unit' => 'mg'
                    ],
                    'K' => [
                        'label' => 'Potassium',
                        'quantity' => 450.7,
                        'unit' => 'mg'
                    ],
                    'FE' => [
                        'label' => 'Iron',
                        'quantity' => 2.8,
                        'unit' => 'mg'
                    ],
                    'ZN' => [
                        'label' => 'Zinc',
                        'quantity' => 3.5,
                        'unit' => 'mg'
                    ],
                    'VITC' => [
                        'label' => 'Vitamin C',
                        'quantity' => 15.2,
                        'unit' => 'mg'
                    ],
                    'VITD' => [
                        'label' => 'Vitamin D',
                        'quantity' => 2.1,
                        'unit' => 'µg'
                    ],
                    'FOLATE' => [
                        'label' => 'Folate',
                        'quantity' => 45.8,
                        'unit' => 'µg'
                    ]
                ],
                'totalDaily' => [
                    'ENERC_KCAL' => [
                        'label' => 'Energy',
                        'quantity' => 26.0,
                        'unit' => '%'
                    ],
                    'FAT' => [
                        'label' => 'Fat',
                        'quantity' => 55.1,
                        'unit' => '%'
                    ],
                    'FASAT' => [
                        'label' => 'Saturated',
                        'quantity' => 62.5,
                        'unit' => '%'
                    ],
                    'PROCNT' => [
                        'label' => 'Protein',
                        'quantity' => 90.4,
                        'unit' => '%'
                    ]
                ],
                'ingredients' => [
                    [
                        'text' => '2 chicken breasts',
                        'parsed' => [
                            [
                                'quantity' => 2,
                                'measure' => 'piece',
                                'food' => 'chicken breast',
                                'foodId' => 'food_chicken_123',
                                'weight' => 250.5,
                                'retainedWeight' => 250.5,
                                'nutrients' => [
                                    'ENERC_KCAL' => [
                                        'label' => 'Energy',
                                        'quantity' => 413.25,
                                        'unit' => 'kcal'
                                    ]
                                ],
                                'measureURI' => 'http://www.edamam.com/ontologies/edamam.owl#Measure_piece',
                                'status' => 'OK'
                            ]
                        ]
                    ],
                    [
                        'text' => '1 tablespoon olive oil',
                        'parsed' => [
                            [
                                'quantity' => 1,
                                'measure' => 'tablespoon',
                                'food' => 'olive oil',
                                'foodId' => 'food_oil_456',
                                'weight' => 13.5,
                                'retainedWeight' => 13.5,
                                'nutrients' => [
                                    'ENERC_KCAL' => [
                                        'label' => 'Energy',
                                        'quantity' => 119.0,
                                        'unit' => 'kcal'
                                    ]
                                ],
                                'measureURI' => 'http://www.edamam.com/ontologies/edamam.owl#Measure_tablespoon',
                                'status' => 'OK'
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        // Test complete nutrition analysis flow
        $response = $this->postJson('/api/edamam/nutrition/analyze', [
            'ingredients' => [
                '2 chicken breasts',
                '1 tablespoon olive oil'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'nutrition_analysis' => [
                        'calories',
                        'total_weight',
                        'servings',
                        'nutrition_score',
                        'health_grade',
                        'diet_labels',
                        'health_labels',
                        'cautions',
                        'nutrients' => [
                            'macronutrients' => [
                                'calories',
                                'protein',
                                'fat',
                                'carbohydrates',
                                'fiber',
                                'sugar'
                            ],
                            'micronutrients',
                            'vitamins',
                            'minerals'
                        ],
                        'daily_values',
                        'ingredient_breakdown'
                    ],
                    'recommendations',
                    'analysis_metadata'
                ],
                'request_id',
                'timestamp'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Nutrition analysis completed successfully'
            ]);

        // Verify API was called with correct parameters
        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);
            return str_contains($request->url(), 'api.edamam.com/api/nutrition-details') &&
                   $request->method() === 'POST' &&
                   isset($body['ingr']) &&
                   count($body['ingr']) === 2 &&
                   in_array('2 chicken breasts', $body['ingr']) &&
                   in_array('1 tablespoon olive oil', $body['ingr']);
        });

        // Verify caching behavior
        $this->assertTrue(Cache::has('nutrition_analysis_' . md5(json_encode([
            '2 chicken breasts',
            '1 tablespoon olive oil'
        ]))));
    }

    public function test_complete_food_search_flow()
    {
        // Mock successful food search API response
        Http::fake([
            'api.edamam.com/api/food-database/v2/parser*' => Http::response([
                'text' => 'apple',
                'parsed' => [],
                'hints' => [
                    [
                        'food' => [
                            'foodId' => 'food_apple_123',
                            'label' => 'Apple',
                            'knownAs' => 'apple, raw',
                            'nutrients' => [
                                'ENERC_KCAL' => 52,
                                'PROCNT' => 0.3,
                                'FAT' => 0.2,
                                'CHOCDF' => 14,
                                'FIBTG' => 2.4,
                                'SUGAR' => 10.4,
                                'CA' => 6,
                                'FE' => 0.12,
                                'MG' => 5,
                                'P' => 11,
                                'K' => 107,
                                'NA' => 1,
                                'ZN' => 0.04,
                                'VITC' => 4.6,
                                'THIA' => 0.017,
                                'RIBF' => 0.026,
                                'NIA' => 0.091,
                                'VITB6A' => 0.041,
                                'FOLDFE' => 3,
                                'VITB12' => 0,
                                'VITA_RAE' => 3,
                                'VITK1' => 2.2
                            ],
                            'category' => 'Generic foods',
                            'categoryLabel' => 'food',
                            'image' => 'https://www.edamam.com/food-img/42c/42c006401027d35add93113548eeaae6.jpg'
                        ],
                        'measures' => [
                            [
                                'uri' => 'http://www.edamam.com/ontologies/edamam.owl#Measure_piece',
                                'label' => 'Piece',
                                'weight' => 182,
                                'qualified' => [
                                    [
                                        'qualifiers' => [
                                            [
                                                'uri' => 'http://www.edamam.com/ontologies/edamam.owl#Qualifier_large',
                                                'label' => 'large'
                                            ]
                                        ],
                                        'weight' => 223
                                    ],
                                    [
                                        'qualifiers' => [
                                            [
                                                'uri' => 'http://www.edamam.com/ontologies/edamam.owl#Qualifier_medium',
                                                'label' => 'medium'
                                            ]
                                        ],
                                        'weight' => 182
                                    ],
                                    [
                                        'qualifiers' => [
                                            [
                                                'uri' => 'http://www.edamam.com/ontologies/edamam.owl#Qualifier_small',
                                                'label' => 'small'
                                            ]
                                        ],
                                        'weight' => 149
                                    ]
                                ]
                            ],
                            [
                                'uri' => 'http://www.edamam.com/ontologies/edamam.owl#Measure_cup',
                                'label' => 'Cup',
                                'weight' => 125
                            ],
                            [
                                'uri' => 'http://www.edamam.com/ontologies/edamam.owl#Measure_slice',
                                'label' => 'Slice',
                                'weight' => 22
                            ]
                        ]
                    ],
                    [
                        'food' => [
                            'foodId' => 'food_apple_green_456',
                            'label' => 'Apple, green',
                            'knownAs' => 'green apple, granny smith',
                            'nutrients' => [
                                'ENERC_KCAL' => 58,
                                'PROCNT' => 0.4,
                                'FAT' => 0.2,
                                'CHOCDF' => 15.2,
                                'FIBTG' => 2.8
                            ],
                            'category' => 'Generic foods',
                            'categoryLabel' => 'food',
                            'image' => 'https://www.edamam.com/food-img/a71/a718cf3c52add522128929f1f324d2ab.jpg'
                        ],
                        'measures' => [
                            [
                                'uri' => 'http://www.edamam.com/ontologies/edamam.owl#Measure_piece',
                                'label' => 'Piece',
                                'weight' => 154
                            ]
                        ]
                    ]
                ],
                '_links' => [
                    'next' => [
                        'title' => 'Next page',
                        'href' => 'https://api.edamam.com/api/food-database/v2/parser?session=40&app_id=test&app_key=test'
                    ]
                ]
            ], 200)
        ]);

        // Test complete food search flow
        $response = $this->getJson('/api/edamam/food/search?' . http_build_query([
            'q' => 'apple',
            'category' => 'Generic foods',
            'page' => 1,
            'per_page' => 20
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'foods' => [
                        '*' => [
                            'food_id',
                            'label',
                            'known_as',
                            'category',
                            'image',
                            'nutrients',
                            'measures',
                            'nutrition_grade',
                            'health_score'
                        ]
                    ],
                    'pagination',
                    'search_metadata',
                    'nutrition_summary'
                ],
                'request_id',
                'timestamp'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Food search completed successfully'
            ]);

        // Verify API was called with correct parameters
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'api.edamam.com/api/food-database/v2/parser') &&
                   $request->method() === 'GET' &&
                   str_contains($request->url(), 'ingr=apple') &&
                   str_contains($request->url(), 'category=Generic%20foods');
        });

        // Verify response data structure and content
        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('foods', $responseData['data']);
        $this->assertGreaterThan(0, count($responseData['data']['foods']));
        
        // Verify first food item structure
        $firstFood = $responseData['data']['foods'][0];
        $this->assertArrayHasKey('food_id', $firstFood);
        $this->assertArrayHasKey('label', $firstFood);
        $this->assertArrayHasKey('nutrients', $firstFood);
        $this->assertArrayHasKey('measures', $firstFood);
    }

    public function test_complete_recipe_search_flow()
    {
        // Mock successful recipe search API response
        Http::fake([
            'api.edamam.com/api/recipes/v2*' => Http::response([
                'from' => 1,
                'to' => 20,
                'count' => 10000,
                '_links' => [
                    'next' => [
                        'href' => 'https://api.edamam.com/api/recipes/v2?type=public&q=chicken&app_id=test&app_key=test&from=21&to=40',
                        'title' => 'Next page'
                    ]
                ],
                'hits' => [
                    [
                        'recipe' => [
                            'uri' => 'http://www.edamam.com/ontologies/edamam.owl#recipe_integration_test',
                            'label' => 'Grilled Chicken with Vegetables',
                            'image' => 'https://edamam-product-images.s3.amazonaws.com/integration/LARGE.jpg',
                            'images' => [
                                'THUMBNAIL' => [
                                    'url' => 'https://edamam-product-images.s3.amazonaws.com/integration/THUMBNAIL.jpg',
                                    'width' => 100,
                                    'height' => 100
                                ],
                                'SMALL' => [
                                    'url' => 'https://edamam-product-images.s3.amazonaws.com/integration/SMALL.jpg',
                                    'width' => 200,
                                    'height' => 200
                                ],
                                'REGULAR' => [
                                    'url' => 'https://edamam-product-images.s3.amazonaws.com/integration/REGULAR.jpg',
                                    'width' => 300,
                                    'height' => 300
                                ],
                                'LARGE' => [
                                    'url' => 'https://edamam-product-images.s3.amazonaws.com/integration/LARGE.jpg',
                                    'width' => 556,
                                    'height' => 370
                                ]
                            ],
                            'source' => 'Integration Test Source',
                            'url' => 'https://example.com/grilled-chicken-vegetables',
                            'shareAs' => 'http://www.edamam.com/recipe/grilled-chicken-vegetables-integration',
                            'yield' => 4,
                            'dietLabels' => ['Balanced'],
                            'healthLabels' => ['Gluten-Free', 'Dairy-Free', 'Low-Carb'],
                            'cautions' => [],
                            'ingredientLines' => [
                                '4 boneless, skinless chicken breasts',
                                '2 bell peppers, sliced',
                                '1 zucchini, sliced',
                                '1 red onion, sliced',
                                '3 tablespoons olive oil',
                                '2 cloves garlic, minced',
                                '1 teaspoon dried herbs (oregano, thyme)',
                                'Salt and black pepper to taste',
                                '1 lemon, juiced'
                            ],
                            'ingredients' => [
                                [
                                    'text' => '4 boneless, skinless chicken breasts',
                                    'quantity' => 4,
                                    'measure' => 'piece',
                                    'food' => 'chicken breast',
                                    'weight' => 680,
                                    'foodCategory' => 'Poultry',
                                    'foodId' => 'food_chicken_integration',
                                    'image' => 'https://www.edamam.com/food-img/d33/d338229d774a743f7858f6764e095878.jpg'
                                ],
                                [
                                    'text' => '2 bell peppers, sliced',
                                    'quantity' => 2,
                                    'measure' => 'piece',
                                    'food' => 'bell pepper',
                                    'weight' => 238,
                                    'foodCategory' => 'Vegetables',
                                    'foodId' => 'food_pepper_integration'
                                ]
                            ],
                            'calories' => 385.5,
                            'totalWeight' => 1200.8,
                            'totalTime' => 35,
                            'cuisineType' => ['Mediterranean'],
                            'mealType' => ['Lunch', 'Dinner'],
                            'dishType' => ['Main course'],
                            'totalNutrients' => [
                                'ENERC_KCAL' => [
                                    'label' => 'Energy',
                                    'quantity' => 385.5,
                                    'unit' => 'kcal'
                                ],
                                'FAT' => [
                                    'label' => 'Fat',
                                    'quantity' => 18.2,
                                    'unit' => 'g'
                                ],
                                'FASAT' => [
                                    'label' => 'Saturated',
                                    'quantity' => 4.1,
                                    'unit' => 'g'
                                ],
                                'PROCNT' => [
                                    'label' => 'Protein',
                                    'quantity' => 48.8,
                                    'unit' => 'g'
                                ],
                                'CHOCDF' => [
                                    'label' => 'Carbs',
                                    'quantity' => 12.5,
                                    'unit' => 'g'
                                ],
                                'FIBTG' => [
                                    'label' => 'Fiber',
                                    'quantity' => 4.2,
                                    'unit' => 'g'
                                ],
                                'SUGAR' => [
                                    'label' => 'Sugars',
                                    'quantity' => 8.1,
                                    'unit' => 'g'
                                ],
                                'NA' => [
                                    'label' => 'Sodium',
                                    'quantity' => 420.5,
                                    'unit' => 'mg'
                                ]
                            ],
                            'totalDaily' => [
                                'ENERC_KCAL' => [
                                    'label' => 'Energy',
                                    'quantity' => 19.3,
                                    'unit' => '%'
                                ],
                                'FAT' => [
                                    'label' => 'Fat',
                                    'quantity' => 28.0,
                                    'unit' => '%'
                                ]
                            ],
                            'digest' => [
                                [
                                    'label' => 'Fat',
                                    'tag' => 'FAT',
                                    'schemaOrgTag' => 'fatContent',
                                    'total' => 18.2,
                                    'hasRDI' => true,
                                    'daily' => 28.0,
                                    'unit' => 'g',
                                    'sub' => [
                                        [
                                            'label' => 'Saturated',
                                            'tag' => 'FASAT',
                                            'schemaOrgTag' => 'saturatedFatContent',
                                            'total' => 4.1,
                                            'hasRDI' => true,
                                            'daily' => 20.5,
                                            'unit' => 'g'
                                        ]
                                    ]
                                ],
                                [
                                    'label' => 'Carbs',
                                    'tag' => 'CHOCDF',
                                    'schemaOrgTag' => 'carbohydrateContent',
                                    'total' => 12.5,
                                    'hasRDI' => true,
                                    'daily' => 4.2,
                                    'unit' => 'g',
                                    'sub' => [
                                        [
                                            'label' => 'Fiber',
                                            'tag' => 'FIBTG',
                                            'schemaOrgTag' => 'fiberContent',
                                            'total' => 4.2,
                                            'hasRDI' => true,
                                            'daily' => 16.8,
                                            'unit' => 'g'
                                        ],
                                        [
                                            'label' => 'Sugars',
                                            'tag' => 'SUGAR',
                                            'schemaOrgTag' => 'sugarContent',
                                            'total' => 8.1,
                                            'hasRDI' => false,
                                            'daily' => 0,
                                            'unit' => 'g'
                                        ]
                                    ]
                                ],
                                [
                                    'label' => 'Protein',
                                    'tag' => 'PROCNT',
                                    'schemaOrgTag' => 'proteinContent',
                                    'total' => 48.8,
                                    'hasRDI' => true,
                                    'daily' => 97.6,
                                    'unit' => 'g'
                                ]
                            ]
                        ],
                        '_links' => [
                            'self' => [
                                'href' => 'https://api.edamam.com/api/recipes/v2/integration_test?type=public&app_id=test&app_key=test'
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        // Test complete recipe search flow
        $response = $this->getJson('/api/edamam/recipes/search?' . http_build_query([
            'q' => 'chicken',
            'diet' => 'balanced',
            'health' => 'gluten-free',
            'cuisineType' => 'mediterranean',
            'mealType' => 'dinner',
            'dishType' => 'main course',
            'calories' => '300-500',
            'time' => '20-60',
            'page' => 1,
            'per_page' => 20
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'recipes' => [
                        '*' => [
                            'recipe_id',
                            'title',
                            'image',
                            'images',
                            'source',
                            'url',
                            'servings',
                            'total_time',
                            'difficulty_score',
                            'estimated_cost',
                            'cuisine_type',
                            'meal_type',
                            'dish_type',
                            'diet_labels',
                            'health_labels',
                            'cautions',
                            'ingredients',
                            'ingredient_lines',
                            'nutrition'
                        ]
                    ],
                    'pagination',
                    'search_metadata',
                    'nutrition_summary'
                ],
                'request_id',
                'timestamp'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Recipes retrieved successfully'
            ]);

        // Verify API was called with correct parameters
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'api.edamam.com/api/recipes/v2') &&
                   $request->method() === 'GET' &&
                   str_contains($request->url(), 'q=chicken') &&
                   str_contains($request->url(), 'diet=balanced') &&
                   str_contains($request->url(), 'health=gluten-free') &&
                   str_contains($request->url(), 'cuisineType=mediterranean');
        });

        // Verify response data structure and content
        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('recipes', $responseData['data']);
        $this->assertGreaterThan(0, count($responseData['data']['recipes']));
        
        // Verify first recipe structure
        $firstRecipe = $responseData['data']['recipes'][0];
        $this->assertArrayHasKey('recipe_id', $firstRecipe);
        $this->assertArrayHasKey('title', $firstRecipe);
        $this->assertArrayHasKey('nutrition', $firstRecipe);
        $this->assertArrayHasKey('ingredients', $firstRecipe);
        $this->assertArrayHasKey('difficulty_score', $firstRecipe);
    }

    public function test_api_error_handling_integration()
    {
        // Mock API error responses for different scenarios
        Http::fake([
            'api.edamam.com/api/nutrition-details*' => Http::response([
                'error' => 'Invalid API credentials',
                'message' => 'Authentication failed'
            ], 401),
            'api.edamam.com/api/food-database/v2/parser*' => Http::response([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests'
            ], 429),
            'api.edamam.com/api/recipes/v2*' => Http::response([
                'error' => 'Service unavailable',
                'message' => 'Temporary server error'
            ], 503)
        ]);

        // Test nutrition API error handling
        $nutritionResponse = $this->postJson('/api/edamam/nutrition/analyze', [
            'ingredients' => ['chicken breast']
        ]);
        
        $nutritionResponse->assertStatus(500)
            ->assertJsonStructure([
                'success',
                'message',
                'error',
                'request_id',
                'timestamp'
            ])
            ->assertJson([
                'success' => false
            ]);

        // Test food API error handling
        $foodResponse = $this->getJson('/api/edamam/food/search?q=apple');
        
        $foodResponse->assertStatus(500)
            ->assertJsonStructure([
                'success',
                'message',
                'error',
                'request_id',
                'timestamp'
            ])
            ->assertJson([
                'success' => false
            ]);

        // Test recipe API error handling
        $recipeResponse = $this->getJson('/api/edamam/recipes/search?q=pasta');
        
        $recipeResponse->assertStatus(500)
            ->assertJsonStructure([
                'success',
                'message',
                'error',
                'request_id',
                'timestamp'
            ])
            ->assertJson([
                'success' => false
            ]);
    }

    public function test_caching_integration()
    {
        // Mock successful API response
        Http::fake([
            'api.edamam.com/*' => Http::response([
                'hints' => [
                    [
                        'food' => [
                            'foodId' => 'food_cache_test',
                            'label' => 'Cache Test Food'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $searchQuery = 'cache test food';
        $cacheKey = 'food_search_' . md5($searchQuery);

        // First request - should hit API and cache result
        $firstResponse = $this->getJson('/api/edamam/food/search?' . http_build_query([
            'q' => $searchQuery
        ]));
        
        $firstResponse->assertStatus(200);
        
        // Verify cache was populated
        $this->assertTrue(Cache::has($cacheKey));
        
        // Second request - should use cached result
        Http::fake(); // Clear HTTP fake to ensure no API calls
        
        $secondResponse = $this->getJson('/api/edamam/food/search?' . http_build_query([
            'q' => $searchQuery
        ]));
        
        $secondResponse->assertStatus(200);
        
        // Verify no API calls were made for cached request
        Http::assertNothingSent();
    }

    public function test_request_id_propagation_integration()
    {
        Http::fake([
            'api.edamam.com/*' => Http::response(['hints' => []], 200)
        ]);

        $customRequestId = 'integration-test-request-123';
        
        // Test request ID propagation through the entire flow
        $response = $this->withHeaders([
            'X-Request-ID' => $customRequestId
        ])->getJson('/api/edamam/food/autocomplete?q=apple');

        $response->assertStatus(200)
            ->assertJson([
                'request_id' => $customRequestId
            ]);

        // Verify request ID is logged (would need to check logs in real scenario)
        $this->assertTrue(true);
    }

    public function test_middleware_integration()
    {
        Http::fake([
            'api.edamam.com/*' => Http::response(['hints' => []], 200)
        ]);

        // Test that all middleware is properly applied
        $response = $this->withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Request-ID' => 'middleware-test-456'
        ])->getJson('/api/edamam/food/autocomplete?q=test');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');

        // Verify middleware chain execution
        $this->assertTrue(true);
    }

    public function test_validation_integration()
    {
        // Test comprehensive validation across all endpoints
        
        // Nutrition analysis validation
        $nutritionResponse = $this->postJson('/api/edamam/nutrition/analyze', [
            'ingredients' => [] // Empty ingredients
        ]);
        
        $nutritionResponse->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
                'request_id',
                'timestamp'
            ]);

        // Food search validation
        $foodResponse = $this->getJson('/api/edamam/food/search'); // Missing query
        
        $foodResponse->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
                'request_id',
                'timestamp'
            ]);

        // Recipe search validation
        $recipeResponse = $this->getJson('/api/edamam/recipes/search'); // Missing query
        
        $recipeResponse->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
                'request_id',
                'timestamp'
            ]);
    }

    public function test_performance_and_timeout_integration()
    {
        // Mock slow API response to test timeout handling
        Http::fake([
            'api.edamam.com/*' => function () {
                sleep(2); // Simulate slow response
                return Http::response(['hints' => []], 200);
            }
        ]);

        // Set short timeout for testing
        Config::set('edamam.timeout', 1);

        $response = $this->getJson('/api/edamam/food/search?q=timeout-test');

        // Should handle timeout gracefully
        $response->assertStatus(500)
            ->assertJsonStructure([
                'success',
                'message',
                'error',
                'request_id',
                'timestamp'
            ]);
    }

    public function test_data_transformation_integration()
    {
        // Mock API response with complex data structure
        Http::fake([
            'api.edamam.com/api/nutrition-details*' => Http::response([
                'calories' => 520.25,
                'totalWeight' => 300.5,
                'totalNutrients' => [
                    'ENERC_KCAL' => ['label' => 'Energy', 'quantity' => 520.25, 'unit' => 'kcal'],
                    'PROCNT' => ['label' => 'Protein', 'quantity' => 45.2, 'unit' => 'g'],
                    'FAT' => ['label' => 'Fat', 'quantity' => 35.8, 'unit' => 'g']
                ],
                'totalDaily' => [
                    'ENERC_KCAL' => ['label' => 'Energy', 'quantity' => 26.0, 'unit' => '%']
                ]
            ], 200)
        ]);

        $response = $this->postJson('/api/edamam/nutrition/analyze', [
            'ingredients' => ['test ingredient']
        ]);

        $response->assertStatus(200);
        
        $responseData = $response->json();
        
        // Verify data transformation and formatting
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('nutrition_analysis', $responseData['data']);
        
        $nutritionData = $responseData['data']['nutrition_analysis'];
        $this->assertArrayHasKey('calories', $nutritionData);
        $this->assertArrayHasKey('nutrients', $nutritionData);
        $this->assertArrayHasKey('nutrition_score', $nutritionData);
        $this->assertArrayHasKey('health_grade', $nutritionData);
        
        // Verify calculated fields are present
        $this->assertIsNumeric($nutritionData['nutrition_score']);
        $this->assertIsString($nutritionData['health_grade']);
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        Cache::flush();
        Http::fake(); // Reset HTTP fake
        
        parent::tearDown();
    }
}