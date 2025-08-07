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
     * Progressive Data Submission - Step 5: Complete recipe creation
     */
    public function completeRecipe(Request $request, string $id): JsonResponse
    {
        try {
            $product = Product::where('user_id', Auth::id())->findOrFail($id);

            // Validate that all required steps are completed
            if (!$product->ingredients_data || !$product->nutrition_data || !$product->serving_configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recipe is not ready to be completed. Missing required data.',
                    'missing_data' => [
                        'ingredients' => !$product->ingredients_data,
                        'nutrition' => !$product->nutrition_data,
                        'serving_config' => !$product->serving_configuration,
                    ]
                ], 400);
            }

            // Update product status
            $product->update([
                'creation_step' => 'completed',
                'status' => $request->get('publish', false) ? 'published' : 'draft',
            ]);

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
                'image_url' => 'nullable|url|max:2048',
                'image_path' => 'nullable|string|max:500',
                'category_id' => 'nullable|exists:categories,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update product with image and category data
            $updateData = [];
            
            if ($request->has('image_url')) {
                $updateData['image_url'] = $request->image_url;
            }
            
            if ($request->has('image_path')) {
                $updateData['image_path'] = $request->image_path;
            }
            
            if ($request->has('category_id')) {
                $updateData['category_id'] = $request->category_id;
            }

            // Update creation step if this is the first time setting product details
            if ($product->creation_step === 'name_created') {
                $updateData['creation_step'] = 'details_configured';
            }

            $product->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Product details saved successfully',
                'data' => $product->fresh()->load('category')
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving product details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save product details'
            ], 500);
        }
    }
}