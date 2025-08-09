<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Display a listing of the user's products.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Product::where('user_id', Auth::id())
                ->with(['category']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('creation_step')) {
                $query->where('creation_step', $request->creation_step);
            }

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Apply search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'updated_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $perPage = $request->get('per_page', 15);
            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching products: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products'
            ], 500);
        }
    }

    /**
     * Store a new product (recipe) - Step 1: Create with name only
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'category_id' => 'nullable|exists:categories,id',
                'is_public' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $product = Product::create([
                'user_id' => Auth::id(),
                'name' => $request->name,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'is_public' => $request->get('is_public', false),
                'status' => 'draft',
                'creation_step' => 'name_created',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recipe created successfully',
                'data' => $product->load('category')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create recipe'
            ], 500);
        }
    }

    /**
     * Display the specified product.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())
                ->with(['category'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $product
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'category_id' => 'nullable|exists:categories,id',
                'is_public' => 'boolean',
                'status' => 'in:draft,published',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $product->update($request->only([
                'name', 'description', 'category_id', 'is_public', 'status'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Recipe updated successfully',
                'data' => $product->load('category')
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update recipe'
            ], 500);
        }
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Recipe deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete recipe'
            ], 500);
        }
    }

    /**
     * Progressive Data Submission - Step 2: Add ingredients to recipe
     */
    public function addIngredients(Request $request, string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'ingredients' => 'required|array|min:1',
                'ingredients.*.id' => 'required|string',
                'ingredients.*.name' => 'required|string|max:255',
                'ingredients.*.quantity' => 'required|numeric|min:0',
                'ingredients.*.unit' => 'required|string|max:50',
                'ingredients.*.grams' => 'required|numeric|min:0',
                'ingredients.*.waste' => 'numeric|min:0|max:100',
                'ingredients.*.availableMeasures' => 'array',
                'ingredients.*.allergens' => 'array',
                'ingredients.*.nutritionProportion' => 'array|nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Calculate total weight
            $totalWeight = collect($request->ingredients)->sum('grams');

            // Update product with ingredients data
            $product->update([
                'ingredients_data' => $request->ingredients,
                'total_weight' => $totalWeight,
                'creation_step' => 'ingredients_added',
                'ingredients_updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ingredients added successfully',
                'data' => [
                    'product' => $product->fresh(),
                    'total_weight' => $totalWeight,
                    'ingredients_count' => count($request->ingredients)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding ingredients: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add ingredients'
            ], 500);
        }
    }

    /**
     * Progressive Data Submission - Step 3: Save nutrition analysis
     */
    public function saveNutritionData(Request $request, string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nutrition_data' => 'required|array',
                'nutrition_data.calories' => 'required|numeric|min:0',
                'nutrition_data.total_weight' => 'required|numeric|min:0',
                'nutrition_data.yield' => 'required|numeric|min:1',
                'nutrition_data.servings_per_container' => 'required|integer|min:1',
                'nutrition_data.macronutrients' => 'required|array',
                'nutrition_data.vitamins_minerals' => 'required|array',
                'nutrition_data.daily_values' => 'required|array',
                'nutrition_data.per_serving_data' => 'nullable|array',
                'per_serving_data' => 'nullable|array',
                'servings_per_container' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update product with nutrition data
            $product->update([
                'nutrition_data' => $request->nutrition_data,
                'servings_per_container' => $request->servings_per_container,
                'creation_step' => 'nutrition_analyzed',
                'nutrition_updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nutrition data saved successfully',
                'data' => $product->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving nutrition data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save nutrition data'
            ], 500);
        }
    }

    /**
     * Progressive Data Submission - Step 4: Configure serving information
     */
    public function configureServing(Request $request, string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'serving_configuration' => 'required|array',
                'serving_configuration.mode' => 'required|in:package,serving',
                'serving_configuration.servings_per_container' => 'required|integer|min:1',
                'serving_configuration.serving_size_grams' => 'required|numeric|min:0',
                'serving_configuration.net_weight_per_package' => 'nullable|numeric|min:0',
                'serving_configuration.servings_per_package' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update product with serving configuration
            $product->update([
                'serving_configuration' => $request->serving_configuration,
                'servings_per_container' => $request->serving_configuration['servings_per_container'],
                'serving_size_grams' => $request->serving_configuration['serving_size_grams'],
                'creation_step' => 'serving_configured',
                'serving_updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Serving configuration saved successfully',
                'data' => $product->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error configuring serving: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to configure serving'
            ], 500);
        }
    }

    /**
     * Progressive Data Submission - Save ingredient statements
     */
    public function saveIngredientStatements(Request $request, string $id): JsonResponse
    {
        try {
            Log::info('Saving ingredient statements', [
                'product_id' => $id,
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            $product = Product::where('user_id', Auth::id())->findOrFail($id);
            
            Log::info('Product found', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'current_ingredient_statements' => $product->ingredient_statements
            ]);

            $validator = Validator::make($request->all(), [
                'ingredient_statements' => 'required|array',
                'ingredient_statements.*' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed for ingredient statements', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            Log::info('Validation passed, updating product', [
                'ingredient_statements' => $request->ingredient_statements
            ]);

            // Update product with ingredient statements
            $updateResult = $product->update([
                'ingredient_statements' => $request->ingredient_statements,
            ]);

            Log::info('Update result', [
                'update_successful' => $updateResult,
                'updated_statements' => $product->fresh()->ingredient_statements
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ingredient statements saved successfully',
                'data' => $product->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving ingredient statements', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'product_id' => $id,
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save ingredient statements: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save allergens data for a product
     */
    public function saveAllergens(Request $request, $id)
    {
        try {
            Log::info('Saving allergens data', [
                'product_id' => $id,
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            $product = Product::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            // Validate the allergens data structure
            $validated = $request->validate([
                'detected' => 'array',
                'detected.*.name' => 'required|string',
                'detected.*.source' => 'required|string|in:cautions,healthLabels,ingredients',
                'detected.*.confidence' => 'required|string|in:high,medium,low',
                'detected.*.details' => 'nullable|string',
                'manual' => 'array',
                'manual.*.category' => 'required|string',
                'manual.*.subcategory' => 'nullable|string',
                'manual.*.name' => 'required|string',
                'manual.*.customName' => 'nullable|string',
                'statement' => 'nullable|string',
                'displayOnLabel' => 'boolean'
            ]);

            // Save allergens data
            $product->allergens_data = $validated;
            $product->save();

            Log::info('Allergens data saved successfully', [
                'product_id' => $id,
                'user_id' => Auth::id(),
                'detected_count' => count($validated['detected'] ?? []),
                'manual_count' => count($validated['manual'] ?? [])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Allergens data saved successfully',
                'data' => [
                    'allergens_data' => $product->allergens_data
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed for allergens data', [
                'product_id' => $id,
                'user_id' => Auth::id(),
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Product not found for allergens save', [
                'product_id' => $id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error saving allergens data', [
                'product_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save allergens data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Progressive Data Submission - Step 5: Complete recipe creation
     */
    public function completeRecipe(Request $request, string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);

            // Validate that essential required steps are completed
            if (!$product->ingredients_data || !$product->nutrition_data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recipe is not ready to be completed. Missing required data.',
                    'missing_data' => [
                        'ingredients' => !$product->ingredients_data,
                        'nutrition' => !$product->nutrition_data,
                    ]
                ], 400);
            }

            // Create default serving configuration if not present
            if (!$product->serving_configuration) {
                $defaultServingConfig = [
                    'mode' => 'serving',
                    'servings_per_container' => $product->servings_per_container ?? 1,
                    'serving_size_grams' => $product->serving_size_grams ?? ($product->total_weight ?? 100),
                ];
                
                $product->update([
                    'serving_configuration' => $defaultServingConfig,
                    'servings_per_container' => $defaultServingConfig['servings_per_container'],
                    'serving_size_grams' => $defaultServingConfig['serving_size_grams'],
                    'creation_step' => 'serving_configured',
                    'serving_updated_at' => now(),
                ]);
                
                Log::info('Created default serving configuration for product completion', [
                    'product_id' => $id,
                    'serving_config' => $defaultServingConfig
                ]);
            }

            // Update product status and publication settings
            $updateData = [
                'creation_step' => 'completed',
                'status' => $request->get('status', 'draft'),
            ];

            // Handle publication settings
            if ($request->has('is_public')) {
                $updateData['is_public'] = $request->get('is_public', false);
            }

            // Backward compatibility with 'publish' parameter
            if ($request->get('publish', false)) {
                $updateData['status'] = 'published';
            }

            $product->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Recipe completed successfully',
                'data' => $product->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error completing recipe: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete recipe'
            ], 500);
        }
    }

    /**
     * Get recipe creation progress
     */
    public function getProgress(string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);

            $progress = [
                'current_step' => $product->creation_step,
                'steps_completed' => [
                    'name_created' => true,
                    'ingredients_added' => in_array($product->creation_step, ['ingredients_added', 'nutrition_analyzed', 'serving_configured', 'completed']),
                    'nutrition_analyzed' => in_array($product->creation_step, ['nutrition_analyzed', 'serving_configured', 'completed']),
                    'serving_configured' => in_array($product->creation_step, ['serving_configured', 'completed']),
                    'completed' => $product->creation_step === 'completed',
                ],
                'data_available' => [
                    'ingredients' => !empty($product->ingredients_data),
                    'nutrition' => !empty($product->nutrition_data),
                    'serving_config' => !empty($product->serving_configuration),
                ],
                'timestamps' => [
                    'created_at' => $product->created_at,
                    'ingredients_updated_at' => $product->ingredients_updated_at,
                    'nutrition_updated_at' => $product->nutrition_updated_at,
                    'serving_updated_at' => $product->serving_updated_at,
                    'updated_at' => $product->updated_at,
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'product' => $product,
                    'progress' => $progress
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting recipe progress: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recipe progress'
            ], 500);
        }
    }

    /**
     * Update single ingredient in recipe
     */
    public function updateIngredient(Request $request, string $id, string $ingredientId): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'quantity' => 'sometimes|numeric|min:0',
                'unit' => 'sometimes|string|max:50',
                'waste' => 'sometimes|numeric|min:0|max:100',
                'grams' => 'sometimes|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $ingredients = $product->ingredients_data ?? [];
            $ingredientIndex = collect($ingredients)->search(function ($ingredient) use ($ingredientId) {
                return $ingredient['id'] === $ingredientId;
            });

            if ($ingredientIndex === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ingredient not found'
                ], 404);
            }

            // Update ingredient data
            $ingredients[$ingredientIndex] = array_merge($ingredients[$ingredientIndex], $request->only([
                'quantity', 'unit', 'waste', 'grams'
            ]));

            // Recalculate total weight
            $totalWeight = collect($ingredients)->sum('grams');

            // Update product
            $product->update([
                'ingredients_data' => $ingredients,
                'total_weight' => $totalWeight,
                'ingredients_updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ingredient updated successfully',
                'data' => [
                    'ingredient' => $ingredients[$ingredientIndex],
                    'total_weight' => $totalWeight
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating ingredient: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ingredient'
            ], 500);
        }
    }

    /**
     * Remove ingredient from recipe
     */
    public function removeIngredient(string $id, string $ingredientId): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);

            $ingredients = $product->ingredients_data ?? [];
            $ingredients = collect($ingredients)->reject(function ($ingredient) use ($ingredientId) {
                return $ingredient['id'] === $ingredientId;
            })->values()->toArray();

            // Recalculate total weight
            $totalWeight = collect($ingredients)->sum('grams');

            // Update product
            $product->update([
                'ingredients_data' => $ingredients,
                'total_weight' => $totalWeight,
                'ingredients_updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ingredient removed successfully',
                'data' => [
                    'remaining_ingredients' => count($ingredients),
                    'total_weight' => $totalWeight
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error removing ingredient: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove ingredient'
            ], 500);
        }
    }

    /**
     * Extract image URL from Google/web URL for recipe images
     */
    public function extractImageUrl(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'url' => 'required|url|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid URL provided',
                    'errors' => $validator->errors()
                ], 422);
            }

            $url = $request->url;
            
            // Basic URL validation and processing
            $parsedUrl = parse_url($url);
            if (!$parsedUrl || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid URL format'
                ], 422);
            }

            $extractedImageUrl = $this->extractActualImageUrl($url);
            
            return response()->json([
                'success' => true,
                'message' => 'Image URL processed successfully',
                'data' => [
                    'image_url' => $extractedImageUrl,
                    'original_url' => $url,
                    'processed_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error extracting image URL: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process image URL'
            ], 500);
        }
    }

    /**
     * Extract actual image URL from various sources (Google Images, etc.)
     */
    private function extractActualImageUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        
        // Handle Google Images URLs
        if (isset($parsedUrl['host']) && strpos($parsedUrl['host'], 'google.com') !== false) {
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                
                // Extract from imgurl parameter (Google Images)
                if (isset($queryParams['imgurl'])) {
                    $decodedUrl = urldecode($queryParams['imgurl']);
                    Log::info('Extracted Google Images URL: ' . $decodedUrl);
                    return $decodedUrl;
                }
            }
        }
        
        // Handle other image hosting services or direct image URLs
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        $pathInfo = pathinfo($parsedUrl['path'] ?? '');
        
        if (isset($pathInfo['extension']) && in_array(strtolower($pathInfo['extension']), $imageExtensions)) {
            // Direct image URL
            return $url;
        }
        
        // For other URLs, try to extract image URL from common patterns
        // This could be expanded to handle more services like Pinterest, Instagram, etc.
        
        return $url; // Return original URL if no extraction pattern matches
    }

    /**
     * Progressive Data Submission - Save product details (image and category)
     */
    public function saveProductDetails(Request $request, string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'image_url' => 'nullable|string|max:4096', // Increased limit and changed from url to string for flexibility
                'image_path' => 'nullable|string|max:500',
                'category_id' => 'nullable|exists:categories,id',
            ]);

            if ($validator->fails()) {
                Log::error('Product details validation failed', [
                    'product_id' => $id,
                    'request_data' => $request->all(),
                    'validation_errors' => $validator->errors()->toArray()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update product with image and category data
            $updateData = [];
            
            if ($request->has('image_url') && $request->image_url !== null) {
                $updateData['image_url'] = $request->image_url;
            }
            
            if ($request->has('image_path') && $request->image_path !== null) {
                $updateData['image_path'] = $request->image_path;
            }
            
            if ($request->has('category_id') && $request->category_id !== null) {
                $updateData['category_id'] = $request->category_id;
            }

            // Update creation step if this is the first time setting product details
            if ($product->creation_step === 'name_created') {
                $updateData['creation_step'] = 'details_configured';
            }

            // Only update if there's data to update
            if (!empty($updateData)) {
                $product->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product details saved successfully',
                'data' => $product->fresh()->load('category')
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving product details', [
                'product_id' => $id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save product details: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Toggle product favorite status
     */
    public function toggleFavorite(string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);
            
            $product->update([
                'is_favorite' => !$product->is_favorite
            ]);
            
            return response()->json([
                'success' => true,
                'message' => $product->is_favorite ? 'Product added to favorites' : 'Product removed from favorites',
                'data' => [
                    'is_favorite' => $product->is_favorite
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error toggling product favorite: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle favorite status'
            ], 500);
        }
    }
    
    /**
     * Toggle product pin status
     */
    public function togglePin(string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);
            
            $product->update([
                'is_pinned' => !$product->is_pinned
            ]);
            
            return response()->json([
                'success' => true,
                'message' => $product->is_pinned ? 'Product pinned' : 'Product unpinned',
                'data' => [
                    'is_pinned' => $product->is_pinned
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error toggling product pin: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle pin status'
            ], 500);
        }
    }
    
    /**
     * Duplicate a product
     */
    public function duplicate(string $id): JsonResponse
    {
        try {
            $originalProduct = Product::where('user_id', Auth::id())->findOrFail($id);
            
            // Create a new product with the same data
            $newProduct = $originalProduct->replicate();
            $newProduct->name = $originalProduct->name . ' (Copy)';
            $newProduct->creation_step = $originalProduct->creation_step;
            $newProduct->status = 'draft';
            $newProduct->is_pinned = false;
            $newProduct->is_favorite = false;
            $newProduct->created_at = now();
            $newProduct->updated_at = now();
            $newProduct->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Product duplicated successfully',
                'data' => $newProduct->load('category')
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Error duplicating product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate product'
            ], 500);
        }
    }
    
    /**
     * Get trashed products
     */
    public function trashed(Request $request): JsonResponse
    {
        try {
            $query = Product::where('user_id', Auth::id())
                ->onlyTrashed()
                ->with(['category']);
            
            // Apply search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
            
            // Apply sorting
            $sortBy = $request->get('sort_by', 'deleted_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            // Paginate results
            $perPage = $request->get('per_page', 15);
            $products = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching trashed products: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch trashed products'
            ], 500);
        }
    }
    
    /**
     * Restore a trashed product
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->onlyTrashed()->findOrFail($id);
            $product->restore();
            
            return response()->json([
                'success' => true,
                'message' => 'Product restored successfully',
                'data' => $product->load('category')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error restoring product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore product'
            ], 500);
        }
    }
    
    /**
     * Permanently delete a trashed product
     */
    public function forceDelete(string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->onlyTrashed()->findOrFail($id);
            $product->forceDelete();
            
            return response()->json([
                'success' => true,
                'message' => 'Product permanently deleted'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error permanently deleting product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete product'
            ], 500);
        }
    }
    
    /**
     * Get all categories for the authenticated user
     */
    public function getCategories(): JsonResponse
    {
        try {
            $user = Auth::user();
            $categories = Category::forUser($user->id)
                ->withCount('products')
                ->orderBy('name')
                ->get(['id', 'name', 'created_at']);

            return response()->json([
                'success' => true,
                'data' => $categories,
                'message' => 'Categories retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching categories: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories'
            ], 500);
        }
    }
    
    /**
     * Get tags for a specific product
     */
    public function getProductTags(string $id): JsonResponse
    {
        try {
            Log::info("Getting product tags for product ID: {$id}");
            $product = Product::where('user_id', Auth::id())->findOrFail($id);
            
            Log::info("Product found: {$product->name}");
            Log::info("Product nutrition_data exists: " . (!empty($product->nutrition_data) ? 'YES' : 'NO'));
            Log::info("Product nutrition_data content: " . json_encode($product->nutrition_data));
            
            // Extract health labels from nutrition data
            $healthLabels = [];
            if (!empty($product->nutrition_data) && isset($product->nutrition_data['health_labels'])) {
                $healthLabels = $product->nutrition_data['health_labels'];
                Log::info("Health labels found: " . json_encode($healthLabels));
            } else {
                Log::info("No health labels found in nutrition_data");
            }
            
            // Extract diet labels from nutrition data
            $dietLabels = [];
            if (!empty($product->nutrition_data) && isset($product->nutrition_data['diet_labels'])) {
                $dietLabels = $product->nutrition_data['diet_labels'];
                Log::info("Diet labels found: " . json_encode($dietLabels));
            } else {
                Log::info("No diet labels found in nutrition_data");
            }
            
            // Combine health labels and diet labels
            $tags = array_merge($healthLabels, $dietLabels);
            
            // Remove duplicates and return
            $uniqueTags = array_unique($tags);
            
            Log::info("Final unique tags for product {$product->name}: " . json_encode($uniqueTags));
            
            return response()->json([
                'success' => true,
                'data' => array_values($uniqueTags), // Ensure it's a proper array
                'message' => 'Product tags retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching product tags: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve product tags'
            ], 500);
        }
    }
    
    /**
     * Get favorite products
     */
    public function getFavorites(Request $request): JsonResponse
    {
        try {
            $query = Product::where('user_id', Auth::id())
                ->where('is_favorite', true)
                ->with(['category']);

            // Apply search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Apply category filter
            if ($request->has('category')) {
                $query->where('category_id', $request->category);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'updated_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $perPage = $request->get('per_page', 15);
            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching favorite products: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch favorite products'
            ], 500);
        }
    
    }

    /**
     * Upload image file for a product
     */
    public function uploadImage(Request $request, $id)
    {
        try {
            $product = Product::where('user_id', auth()->id())->findOrFail($id);

            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            ]);

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $filename = time() . '_' . $image->getClientOriginalName();
                
                // Store in public/storage/images directory
                $path = $image->storeAs('images', $filename, 'public');
                
                // Update product with image path
                $product->update([
                    'image_path' => $path,
                    'image_url' => null // Clear URL if file is uploaded
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Image uploaded successfully',
                    'data' => [
                        'image_path' => $path,
                        'image_url' => asset('storage/' . $path)
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No image file provided'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Image upload failed', [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Bulk delete products
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|exists:products,id,user_id,' . Auth::id(),
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $ids = $request->input('ids');
            
            // Soft delete all specified products
            Product::where('user_id', Auth::id())
                ->whereIn('id', $ids)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => count($ids) . ' product(s) deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error bulk deleting products: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete products'
            ], 500);
        }
    }

    /**
     * Clear all ingredients and nutrition data from a product
     */
    public function clearIngredients(string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);

            // Clear all ingredient and nutrition related data
            $product->update([
                'ingredients_data' => [],
                'total_weight' => 0,
                'nutrition_data' => null,
                'servings_per_container' => 1,
                'serving_configuration' => null,
                'creation_step' => 'name_created', // Reset to initial step
                'ingredients_updated_at' => now(),
                'nutrition_updated_at' => null,
                'serving_updated_at' => null,
            ]);

            Log::info('All ingredients and nutrition data cleared for product', [
                'product_id' => $id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All ingredients and nutrition data cleared successfully',
                'data' => $product->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing ingredients: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear ingredients'
            ], 500);
        }
    }
}