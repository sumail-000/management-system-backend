<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\Ingredient;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $query = $user->products()->with(['ingredients', 'category', 'nutritionalData']);

        // Apply filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_pinned')) {
            $query->where('is_pinned', $request->boolean('is_pinned'));
        }

        if ($request->has('tags')) {
            $tags = is_array($request->tags) ? $request->tags : [$request->tags];
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereJsonContains('tags', $search);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        // Validate sort parameters
        $allowedSorts = ['name', 'category_id', 'status', 'created_at', 'updated_at', 'is_pinned'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }
        
        // Apply sorting with special handling for category and status
        if ($sortBy === 'category_id') {
            // Sort by category first, then by name within same category
            $query->orderBy('category_id', $sortOrder)
                  ->orderBy('name', 'asc');
        } elseif ($sortBy === 'status') {
            // Sort by status first (published before draft), then by name within same status
            $query->orderByRaw("CASE WHEN status = 'published' THEN 1 WHEN status = 'draft' THEN 2 ELSE 3 END " . ($sortOrder === 'asc' ? 'ASC' : 'DESC'))
                  ->orderBy('name', 'asc');
        } else {
            // Pinned products first if sorting by created_at or updated_at
            if (in_array($sortBy, ['created_at', 'updated_at'])) {
                $query->orderBy('is_pinned', 'desc');
            }
            $query->orderBy($sortBy, $sortOrder);
        }

        // Debug: Log the total count before pagination
        $totalCount = $query->count();
        Log::info('ProductController index - Total products for user', [
            'user_id' => $user->id,
            'total_count' => $totalCount,
            'per_page' => $request->get('per_page', 15),
            'filters' => $request->only(['category_id', 'status', 'is_pinned', 'tags', 'search'])
        ]);
        
        $products = $query->paginate($request->get('per_page', 15));
        
        // Debug: Log the pagination response
        Log::info('ProductController index - Pagination response', [
            'total' => $products->total(),
            'per_page' => $products->perPage(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'from' => $products->firstItem(),
            'to' => $products->lastItem(),
            'data_count' => $products->count()
        ]);

        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Check product limit
        if ($user->hasReachedProductLimit()) {
            return response()->json([
                'message' => 'Product limit reached for your membership plan'
            ], 403);
        }

        // Log incoming request data for debugging
        Log::info('Product creation request received', [
            'user_id' => $user->id,
            'request_data' => $request->all(),
            'has_file' => $request->hasFile('image_file'),
            'file_info' => $request->hasFile('image_file') ? [
                'original_name' => $request->file('image_file')->getClientOriginalName(),
                'mime_type' => $request->file('image_file')->getMimeType(),
                'size' => $request->file('image_file')->getSize()
            ] : null
        ]);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'required|exists:categories,id',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                // Accept both old and new field names for serving information
                'serving_size' => 'nullable|numeric|min:0',
                'serving_unit' => 'nullable|string|max:50',
                'servings_per_container' => 'nullable|integer|min:1',
                'calories_per_serving' => 'nullable|numeric|min:0',
                'portion_size' => 'nullable|string|in:small,medium,large',
                'serving_type' => 'nullable|string|in:main,side',
                'total_servings' => 'nullable|integer|min:1',
                'is_public' => 'boolean',
                'is_pinned' => 'boolean',
                'status' => 'in:draft,published',
                'image_url' => 'nullable|url|max:2048',
                'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
                'ingredients' => 'nullable|array',
                'ingredients.*.id' => 'nullable|string',
                'ingredients.*.name' => 'required_without:ingredients.*.text|string|max:255',
                'ingredients.*.text' => 'required_without:ingredients.*.name|string|max:500',
                'ingredients.*.quantity' => 'nullable|numeric|min:0',
                'ingredients.*.unit' => 'nullable|string|max:50',
                'ingredient_notes' => 'nullable|string|max:2000',
                
                // Recipe details
                'recipe_uri' => 'nullable|string|max:500',
                'recipe_source' => 'nullable|string|max:255',
                'recipe_url' => 'nullable|url|max:2048',
                'prep_time' => 'nullable|integer|min:0',
                'cook_time' => 'nullable|integer|min:0',
                'total_time' => 'nullable|integer|min:0',
                'skill_level' => 'nullable|string|in:beginner,intermediate,advanced',
                'time_category' => 'nullable|string|in:quick,medium,long',
                'cuisine_type' => 'nullable|string|max:100',
                'difficulty' => 'nullable|string|in:easy,medium,hard',
                'total_co2_emissions' => 'nullable|numeric|min:0',
                'co2_emissions_class' => 'nullable|string|max:50',
                'recipe_yield' => 'nullable|integer|min:1',
                'total_weight' => 'nullable|numeric|min:0',
                'weight_per_serving' => 'nullable|numeric|min:0',
                
                // Rich ingredients data
                'rich_ingredients' => 'nullable|string', // JSON string
                
                // Nutrition data
                'nutrition_data' => 'nullable|string', // JSON string
                
                // Labels and tags
                'diet_labels' => 'nullable|array',
                'diet_labels.*' => 'string|max:100',
                'health_labels' => 'nullable|array',
                'health_labels.*' => 'string|max:100',
                'caution_labels' => 'nullable|array',
                'caution_labels.*' => 'string|max:100',
                'meal_types' => 'nullable|array',
                'meal_types.*' => 'string|max:100',
                'dish_types' => 'nullable|array',
                'dish_types.*' => 'string|max:100',
                'recipe_tags' => 'nullable|array',
                'recipe_tags.*' => 'string|max:100',
            ]);
        } catch (ValidationException $e) {
            Log::error('Product validation failed', [
                'user_id' => $user->id,
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            throw $e;
        }

        $validated['user_id'] = $user->id;
        $validated['is_public'] = $validated['is_public'] ?? false;
        $validated['is_pinned'] = $validated['is_pinned'] ?? false;
        $validated['status'] = $validated['status'] ?? 'draft';
        $validated['ingredient_notes'] = $validated['ingredient_notes'] ?? null;
        
        // Handle backward compatibility for old field names
        // Only map if new fields are not provided
        if (isset($validated['calories_per_serving']) && !isset($validated['serving_size'])) {
            $validated['serving_size'] = $validated['calories_per_serving'];
        }
        if (isset($validated['portion_size']) && !isset($validated['serving_unit'])) {
            $validated['serving_unit'] = $validated['portion_size'];
        }
        if (isset($validated['total_servings']) && !isset($validated['servings_per_container'])) {
            $validated['servings_per_container'] = $validated['total_servings'];
        }
        
        // Remove old field names and non-database fields
        unset($validated['calories_per_serving'], $validated['portion_size'], $validated['total_servings'], $validated['serving_type']);

        // Handle image upload
        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $filename = time() . '_' . $user->id . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('products', $filename, 'public');
            $validated['image_path'] = $path;
            // Clear image_url if file is uploaded
            $validated['image_url'] = null;
        } elseif (!empty($validated['image_url'])) {
            // Clear image_path if URL is provided
            $validated['image_path'] = null;
        }

        // Remove image_file from validated data as it's not a database field
        unset($validated['image_file']);
        
        // Extract rich data before creating product
        $ingredientsData = $validated['ingredients'] ?? [];
        $richIngredientsData = $validated['rich_ingredients'] ?? null;
        $nutritionData = $validated['nutrition_data'] ?? null;
        $dietLabels = $validated['diet_labels'] ?? [];
        $healthLabels = $validated['health_labels'] ?? [];
        $cautionLabels = $validated['caution_labels'] ?? [];
        $mealTypes = $validated['meal_types'] ?? [];
        $dishTypes = $validated['dish_types'] ?? [];
        $recipeTags = $validated['recipe_tags'] ?? [];
        
        // Remove non-product table fields from validated data
        unset($validated['ingredients'], $validated['rich_ingredients'], $validated['nutrition_data']);
        unset($validated['diet_labels'], $validated['health_labels'], $validated['caution_labels']);
        unset($validated['meal_types'], $validated['dish_types'], $validated['recipe_tags']);

        $product = Product::create($validated);
        
        // Handle ingredients
        if (!empty($ingredientsData)) {
            $this->syncProductIngredients($product, $ingredientsData, $richIngredientsData);
        }
        
        // Handle nutrition data
        if ($nutritionData) {
            $this->saveNutritionData($product, $nutritionData);
        }
        
        // Handle labels and tags
        $this->saveProductLabels($product, $dietLabels, $healthLabels, $cautionLabels);
        $this->saveProductMealTypes($product, $mealTypes, $dishTypes);
        $this->saveProductRecipeTags($product, $recipeTags);
        
        $product->load(['ingredients', 'nutritionAutoTags', 'category', 'nutritionalData', 'productLabels', 'mealTypes', 'recipeTags']);

        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $product = Product::with(['ingredients', 'nutritionAutoTags', 'labels', 'user:id,name,email', 'category', 'nutritionalData', 'collections'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $product = Product::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            // Accept both old and new field names for serving information
            'serving_size' => 'nullable|numeric|min:0',
            'serving_unit' => 'nullable|string|max:50',
            'servings_per_container' => 'nullable|integer|min:1',
            'calories_per_serving' => 'nullable|numeric|min:0',
            'portion_size' => 'nullable|string|in:small,medium,large',
            'serving_type' => 'nullable|string|in:main,side',
            'total_servings' => 'nullable|integer|min:1',
            'is_public' => 'boolean',
            'is_pinned' => 'boolean',
            'status' => 'in:draft,published',
            'image_url' => 'nullable|url|max:2048',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'ingredients' => 'nullable|array',
            'ingredients.*.id' => 'nullable|string',
            'ingredients.*.name' => 'required_without:ingredients.*.text|string|max:255',
            'ingredients.*.text' => 'required_without:ingredients.*.name|string|max:500',
            'ingredients.*.quantity' => 'nullable|numeric|min:0',
            'ingredients.*.unit' => 'nullable|string|max:50',
            'ingredient_notes' => 'nullable|string|max:2000',
            // Recipe-related fields
            'recipe_uri' => 'nullable|string|max:500',
            'recipe_source' => 'nullable|string|max:255',
            'recipe_url' => 'nullable|url|max:2048',
            // Time fields (in minutes)
            'prep_time' => 'nullable|integer|min:0',
            'cook_time' => 'nullable|integer|min:0',
            'total_time' => 'nullable|integer|min:0',
            // Classification fields
            'skill_level' => 'nullable|string|in:beginner,intermediate,advanced',
            'time_category' => 'nullable|string|in:quick,medium,long',
            'cuisine_type' => 'nullable|string|max:100',
            'difficulty' => 'nullable|string|in:easy,medium,hard',
            // Environmental data
            'total_co2_emissions' => 'nullable|numeric|min:0',
            'co2_emissions_class' => 'nullable|string|max:50',
            // Recipe yield and serving info
            'recipe_yield' => 'nullable|integer|min:1',
            'total_weight' => 'nullable|numeric|min:0',
            'weight_per_serving' => 'nullable|numeric|min:0',
            // Rich data fields
            'rich_ingredients' => 'nullable|string', // JSON string
            'nutrition_data' => 'nullable|string', // JSON string
            // Labels and tags
            'diet_labels' => 'nullable|array',
            'diet_labels.*' => 'string|max:100',
            'health_labels' => 'nullable|array',
            'health_labels.*' => 'string|max:100',
            'caution_labels' => 'nullable|array',
            'caution_labels.*' => 'string|max:100',
            'meal_types' => 'nullable|array',
            'meal_types.*' => 'string|max:100',
            'dish_types' => 'nullable|array',
            'dish_types.*' => 'string|max:100',
            'recipe_tags' => 'nullable|array',
            'recipe_tags.*' => 'string|max:100',
        ]);

        // Handle backward compatibility for old field names
        // Only map if new fields are not provided
        if (isset($validated['calories_per_serving']) && !isset($validated['serving_size'])) {
            $validated['serving_size'] = $validated['calories_per_serving'];
        }
        if (isset($validated['portion_size']) && !isset($validated['serving_unit'])) {
            $validated['serving_unit'] = $validated['portion_size'];
        }
        if (isset($validated['total_servings']) && !isset($validated['servings_per_container'])) {
            $validated['servings_per_container'] = $validated['total_servings'];
        }
        
        // Remove old field names and non-database fields
        unset($validated['calories_per_serving'], $validated['portion_size'], $validated['total_servings'], $validated['serving_type']);

        // Handle image upload for updates
        if ($request->hasFile('image_file')) {
            // Delete old image file if exists and not used by other products
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                // Check if any other products are using the same image
                $otherProductsUsingImage = Product::withTrashed()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $product->id)
                    ->where('image_path', $product->image_path)
                    ->exists();
                
                // Only delete the image file if no other products are using it
                if (!$otherProductsUsingImage) {
                    Storage::disk('public')->delete($product->image_path);
                }
            }
            
            $file = $request->file('image_file');
            $filename = time() . '_' . $user->id . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('products', $filename, 'public');
            $validated['image_path'] = $path;
            $validated['image_url'] = null;
        } elseif (isset($validated['image_url'])) {
            // If URL is provided, clear the file path and delete old file if not used by others
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                // Check if any other products are using the same image
                $otherProductsUsingImage = Product::withTrashed()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $product->id)
                    ->where('image_path', $product->image_path)
                    ->exists();
                
                // Only delete the image file if no other products are using it
                if (!$otherProductsUsingImage) {
                    Storage::disk('public')->delete($product->image_path);
                }
            }
            $validated['image_path'] = null;
        }

        // Remove image_file from validated data
        unset($validated['image_file']);
        
        // Extract rich data before updating product
        $ingredientsData = $validated['ingredients'] ?? null;
        $richIngredientsData = $validated['rich_ingredients'] ?? null;
        $nutritionData = $validated['nutrition_data'] ?? null;
        $dietLabels = $validated['diet_labels'] ?? [];
        $healthLabels = $validated['health_labels'] ?? [];
        $cautionLabels = $validated['caution_labels'] ?? [];
        $mealTypes = $validated['meal_types'] ?? [];
        $dishTypes = $validated['dish_types'] ?? [];
        $recipeTags = $validated['recipe_tags'] ?? [];
        
        // Remove non-product table fields from validated data
        unset($validated['ingredients'], $validated['rich_ingredients'], $validated['nutrition_data']);
        unset($validated['diet_labels'], $validated['health_labels'], $validated['caution_labels']);
        unset($validated['meal_types'], $validated['dish_types'], $validated['recipe_tags']);

        $product->update($validated);
        
        // Handle ingredients if provided
        if ($ingredientsData !== null) {
            $this->syncProductIngredients($product, $ingredientsData, $richIngredientsData);
        }
        
        // Handle nutrition data if provided
        if ($nutritionData) {
            $this->saveNutritionData($product, $nutritionData);
        }
        
        // Handle labels and tags if provided
        if (!empty($dietLabels) || !empty($healthLabels) || !empty($cautionLabels)) {
            $this->saveProductLabels($product, $dietLabels, $healthLabels, $cautionLabels);
        }
        
        if (!empty($mealTypes) || !empty($dishTypes)) {
            $this->saveProductMealTypes($product, $mealTypes, $dishTypes);
        }
        
        if (!empty($recipeTags)) {
            $this->saveProductRecipeTags($product, $recipeTags);
        }
        
        $product->load(['ingredients', 'nutritionAutoTags', 'category', 'nutritionalData', 'productLabels', 'mealTypes', 'recipeTags']);

        return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $product = Product::where('user_id', $user->id)->findOrFail($id);
        
        // Delete associated image file if exists and not used by other products
        if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
            // Check if any other products (including soft-deleted ones) are using the same image
            $otherProductsUsingImage = Product::withTrashed()
                ->where('user_id', $user->id)
                ->where('id', '!=', $product->id)
                ->where('image_path', $product->image_path)
                ->exists();
            
            // Only delete the image file if no other products are using it
            if (!$otherProductsUsingImage) {
                Storage::disk('public')->delete($product->image_path);
            }
        }
        
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * Sync ingredients with the product
     */
    private function syncProductIngredients(Product $product, array $ingredientsData, ?string $richIngredientsData = null): void
    {
        $syncData = [];
        $richIngredients = [];
        
        // Parse rich ingredients data if provided
        if ($richIngredientsData) {
            try {
                $richIngredients = json_decode($richIngredientsData, true) ?? [];
            } catch (Exception $e) {
                Log::warning('Failed to parse rich ingredients data', ['error' => $e->getMessage()]);
            }
        }
        
        foreach ($ingredientsData as $index => $ingredientData) {
            // Handle both old structured format and new free text format
            if (isset($ingredientData['text'])) {
                // New free text format
                $ingredientText = trim($ingredientData['text']);
                
                if (empty($ingredientText)) {
                    continue;
                }
                
                // Find or create ingredient using the full text as name
                $ingredient = Ingredient::firstOrCreate(
                    ['name' => $ingredientText],
                    ['description' => null]
                );
                
                // Get rich data for this ingredient if available
                $richData = $richIngredients[$index] ?? [];
                
                // Add to sync data with order and rich data
                $syncData[$ingredient->id] = [
                    'order' => $index + 1,
                    'amount' => null,
                    'unit' => null,
                    'text' => $ingredientText,
                    'quantity' => $richData['quantity'] ?? null,
                    'measure' => $richData['measure'] ?? null,
                    'weight' => $richData['weight'] ?? null,
                    'food_category' => $richData['foodCategory'] ?? null,
                    'food_id' => $richData['foodId'] ?? null,
                    'image_url' => $richData['image'] ?? null,
                    'is_main_ingredient' => $richData['isMainIngredient'] ?? false,
                    'nutrition_data' => isset($richData['nutritionData']) ? json_encode($richData['nutritionData']) : null,
                    'allergens' => isset($richData['allergens']) ? json_encode($richData['allergens']) : null,
                ];
            } else {
                // Legacy structured format (for backward compatibility)
                $ingredientName = trim($ingredientData['name'] ?? '');
                
                if (empty($ingredientName)) {
                    continue;
                }
                
                // Find or create ingredient
                $ingredient = Ingredient::firstOrCreate(
                    ['name' => $ingredientName],
                    ['description' => null]
                );
                
                // Get rich data for this ingredient if available
                $richData = $richIngredients[$index] ?? [];
                
                // Add to sync data with order, amount (quantity), unit, and rich data
                $syncData[$ingredient->id] = [
                    'order' => $index + 1,
                    'amount' => isset($ingredientData['quantity']) ? (float)$ingredientData['quantity'] : null,
                    'unit' => $ingredientData['unit'] ?? null,
                    'text' => $ingredientName,
                    'quantity' => $richData['quantity'] ?? ($ingredientData['quantity'] ?? null),
                    'measure' => $richData['measure'] ?? ($ingredientData['unit'] ?? null),
                    'weight' => $richData['weight'] ?? null,
                    'food_category' => $richData['foodCategory'] ?? null,
                    'food_id' => $richData['foodId'] ?? null,
                    'image_url' => $richData['image'] ?? null,
                    'is_main_ingredient' => $richData['isMainIngredient'] ?? false,
                    'nutrition_data' => isset($richData['nutritionData']) ? json_encode($richData['nutritionData']) : null,
                    'allergens' => isset($richData['allergens']) ? json_encode($richData['allergens']) : null,
                ];
            }
        }
        
        // Sync ingredients with the product
        $product->ingredients()->sync($syncData);
    }

    /**
     * Save nutrition data for the product
     */
    private function saveNutritionData(Product $product, string $nutritionDataJson): void
    {
        try {
            $nutritionData = json_decode($nutritionDataJson, true);
            if (!$nutritionData) {
                return;
            }

            // Create or update nutritional data record
            $product->nutritionalData()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'calories' => $nutritionData['calories'] ?? null,
                    'total_weight' => $nutritionData['totalWeight'] ?? null,
                    'servings' => $nutritionData['servings'] ?? null,
                    'diet_labels' => isset($nutritionData['dietLabels']) ? json_encode($nutritionData['dietLabels']) : null,
                    'health_labels' => isset($nutritionData['healthLabels']) ? json_encode($nutritionData['healthLabels']) : null,
                    'cautions' => isset($nutritionData['cautions']) ? json_encode($nutritionData['cautions']) : null,
                    'total_nutrients' => isset($nutritionData['totalNutrients']) ? json_encode($nutritionData['totalNutrients']) : null,
                    'total_daily' => isset($nutritionData['totalDaily']) ? json_encode($nutritionData['totalDaily']) : null,
                    'total_nutrients_kcal' => isset($nutritionData['totalNutrientsKCal']) ? json_encode($nutritionData['totalNutrientsKCal']) : null,
                    'total_co2_emissions' => $nutritionData['totalCO2Emissions'] ?? null,
                    'co2_emissions_class' => $nutritionData['co2EmissionsClass'] ?? null,
                    'analysis_source' => 'edamam',
                    'analysis_version' => $nutritionData['analysisVersion'] ?? null,
                    'request_id' => $nutritionData['requestId'] ?? null,
                    'is_cached' => $nutritionData['isCached'] ?? false,
                ]
            );
        } catch (Exception $e) {
            Log::error('Failed to save nutrition data', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Save product labels (diet, health, caution)
     */
    private function saveProductLabels(Product $product, array $dietLabels, array $healthLabels, array $cautionLabels): void
    {
        // Clear existing labels
        $product->productLabels()->delete();

        $labelsToInsert = [];

        // Add diet labels
        foreach ($dietLabels as $label) {
            $labelsToInsert[] = [
                'product_id' => $product->id,
                'label_type' => 'diet',
                'label_value' => $label,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Add health labels
        foreach ($healthLabels as $label) {
            $labelsToInsert[] = [
                'product_id' => $product->id,
                'label_type' => 'health',
                'label_value' => $label,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Add caution labels
        foreach ($cautionLabels as $label) {
            $labelsToInsert[] = [
                'product_id' => $product->id,
                'label_type' => 'caution',
                'label_value' => $label,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($labelsToInsert)) {
            $product->productLabels()->insert($labelsToInsert);
        }
    }

    /**
     * Save product meal types (meal and dish types)
     */
    private function saveProductMealTypes(Product $product, array $mealTypes, array $dishTypes): void
    {
        // Clear existing meal types
        $product->mealTypes()->delete();

        $mealTypesToInsert = [];

        // Add meal types
        foreach ($mealTypes as $type) {
            $mealTypesToInsert[] = [
                'product_id' => $product->id,
                'type_category' => 'meal',
                'type_value' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Add dish types
        foreach ($dishTypes as $type) {
            $mealTypesToInsert[] = [
                'product_id' => $product->id,
                'type_category' => 'dish',
                'type_value' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($mealTypesToInsert)) {
            $product->mealTypes()->insert($mealTypesToInsert);
        }
    }

    /**
     * Save product recipe tags
     */
    private function saveProductRecipeTags(Product $product, array $recipeTags): void
    {
        // Clear existing recipe tags
        $product->recipeTags()->delete();

        $tagsToInsert = [];

        // Add recipe tags
        foreach ($recipeTags as $tag) {
            $tagsToInsert[] = [
                'product_id' => $product->id,
                'tag_value' => $tag,
                'tag_source' => 'recipe',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($tagsToInsert)) {
            $product->recipeTags()->insert($tagsToInsert);
        }
    }

    /**
     * Get public products
     */
    public function public(Request $request): JsonResponse
    {
        $query = Product::with(['ingredients', 'nutritionAutoTags', 'user:id,name,company', 'category'])
            ->where('is_public', true);

        // Apply filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('category', function ($categoryQuery) use ($search) {
                      $categoryQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $products = $query->paginate($request->get('per_page', 15));

        return response()->json($products);
    }

    /**
     * Get a specific public product by ID
     */
    public function getPublicById(string $id): JsonResponse
    {
        $product = Product::with(['ingredients', 'nutritionAutoTags', 'user:id,name,company', 'category', 'qrCodes', 'labels'])
            ->where('is_public', true)
            ->findOrFail($id);

        return response()->json($product);
    }

    /**
     * Duplicate an existing product
     */
    public function duplicate(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        
        // Check product limit
        if ($user->hasReachedProductLimit()) {
            return response()->json([
                'message' => 'Product limit reached for your membership plan'
            ], 403);
        }

        $originalProduct = Product::where('user_id', $user->id)->findOrFail($id);
        
        // Create a copy of the product
        $duplicatedData = $originalProduct->toArray();
        unset($duplicatedData['id'], $duplicatedData['created_at'], $duplicatedData['updated_at'], $duplicatedData['deleted_at']);
        
        // Modify the name to indicate it's a copy
        $baseName = $duplicatedData['name'];
        // Remove existing (Copy) suffixes to avoid "copy copy" issue
        $baseName = preg_replace('/\s*\(Copy\)\s*$/', '', $baseName);
        
        // Find the next available copy number
        $copyNumber = 1;
        $newName = $baseName . ' (Copy)';
        
        while (Product::where('user_id', $user->id)
                     ->where('name', $newName)
                     ->exists()) {
            $copyNumber++;
            $newName = $baseName . ' (Copy ' . $copyNumber . ')';
        }
        
        $duplicatedData['name'] = $newName;
        $duplicatedData['is_pinned'] = false; // New duplicated products are not pinned by default
        $duplicatedData['status'] = 'draft'; // New duplicated products start as draft
        
        // Handle image duplication
        if ($originalProduct->image_path && Storage::disk('public')->exists($originalProduct->image_path)) {
            // Get the original file info
            $originalPath = $originalProduct->image_path;
            $pathInfo = pathinfo($originalPath);
            $extension = $pathInfo['extension'] ?? 'jpg';
            
            // Create new filename for the duplicated image
            $newFilename = time() . '_' . $user->id . '_copy_' . $pathInfo['filename'] . '.' . $extension;
            $newPath = 'products/' . $newFilename;
            
            // Copy the file
            if (Storage::disk('public')->copy($originalPath, $newPath)) {
                $duplicatedData['image_path'] = $newPath;
            }
        }
        // Note: image_url is kept as-is since URLs don't need duplication
        
        $duplicatedProduct = Product::create($duplicatedData);
        
        // Copy ingredients relationship if exists
        if ($originalProduct->ingredients()->exists()) {
            $ingredients = $originalProduct->ingredients()->get();
            foreach ($ingredients as $ingredient) {
                $duplicatedProduct->ingredients()->attach($ingredient->id, [
                    'amount' => $ingredient->pivot->amount,
                    'unit' => $ingredient->pivot->unit,
                    'order' => $ingredient->pivot->order,
                ]);
            }
        }
        
        $duplicatedProduct->load(['ingredients', 'nutritionAutoTags', 'category']);
        
        return response()->json($duplicatedProduct, 201);
    }

    /**
     * Toggle pin status of a product
     */
    public function togglePin(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $product = Product::where('user_id', $user->id)->findOrFail($id);
        
        $product->update(['is_pinned' => !$product->is_pinned]);
        
        return response()->json([
            'message' => $product->is_pinned ? 'Product pinned successfully' : 'Product unpinned successfully',
            'is_pinned' => $product->is_pinned
        ]);
    }

    /**
     * Toggle favorite status of a product
     */
    public function toggleFavorite(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $product = Product::where('user_id', $user->id)->findOrFail($id);
        
        $product->update(['is_favorite' => !$product->is_favorite]);
        
        return response()->json([
            'message' => $product->is_favorite ? 'Product added to favorites' : 'Product removed from favorites',
            'is_favorite' => $product->is_favorite
        ]);
    }

    /**
     * Get favorited products
     */
    public function getFavorites(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        
        $query = $user->products()
            ->where('is_favorite', true)
            ->with(['ingredients', 'nutritionAutoTags', 'category']);
        
        // Apply search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereJsonContains('tags', $search);
            });
        }
        
        // Apply category filter
        if ($request->has('category') && $request->category !== 'all') {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('name', $request->category);
            });
        }
        
        // Apply sorting
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        switch ($sortBy) {
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            case 'created_at':
                $query->orderBy('created_at', $sortOrder);
                break;
            case 'category':
                $query->join('categories', 'products.category_id', '=', 'categories.id')
                      ->orderBy('categories.name', $sortOrder)
                      ->select('products.*');
                break;
            default:
                $query->orderBy('updated_at', $sortOrder);
                break;
        }
        
        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);
        
        return response()->json($products);
    }

    /**
     * Get all categories available to the user
     */
    public function getCategories(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $categories = \App\Models\Category::forUser($user->id)
            ->orderBy('name')
            ->get(['id', 'name']);
            
        return response()->json($categories);
    }

    /**
     * Get all unique tags from user's products
     */
    public function getTags(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $products = $user->products()
            ->whereNotNull('tags')
            ->get(['tags']);
            
        $allTags = collect();
        foreach ($products as $product) {
            if ($product->tags) {
                $allTags = $allTags->merge($product->tags);
            }
        }
        
        $uniqueTags = $allTags->unique()->values();
        
        return response()->json($uniqueTags);
    }

    /**
     * Restore a soft-deleted product
     */
    public function restore(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $product = Product::withTrashed()
            ->where('user_id', $user->id)
            ->findOrFail($id);
            
        if (!$product->trashed()) {
            return response()->json(['message' => 'Product is not deleted'], 400);
        }
        
        $product->restore();
        
        return response()->json(['message' => 'Product restored successfully']);
    }

    /**
     * Get trashed products
     */
    public function trashed(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $query = $user->products()->onlyTrashed()->with(['ingredients', 'nutritionAutoTags', 'category']);
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $products = $query->paginate($request->get('per_page', 15));
        
        return response()->json($products);
    }

    /**
     * Permanently delete a product
     */
    public function forceDelete(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $product = Product::withTrashed()
            ->where('user_id', $user->id)
            ->findOrFail($id);
        
        // Delete associated image file if exists and not used by other products
        if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
            // Check if any other products (including soft-deleted ones) are using the same image
            $otherProductsUsingImage = Product::withTrashed()
                ->where('user_id', $user->id)
                ->where('id', '!=', $product->id)
                ->where('image_path', $product->image_path)
                ->exists();
            
            // Only delete the image file if no other products are using it
            if (!$otherProductsUsingImage) {
                Storage::disk('public')->delete($product->image_path);
            }
        }
            
        $product->forceDelete();
        
        return response()->json(['message' => 'Product permanently deleted']);
    }

    /**
     * Convert units to grams for standardization
     */
    public function convertUnits(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
            'from_unit' => 'required|string|in:mg,g,kg,lb,oz,ml',
            'to_unit' => 'required|string|in:mg,g,kg,lb,oz,ml'
        ]);

        $quantity = $validated['quantity'];
        $fromUnit = strtolower($validated['from_unit']);
        $toUnit = strtolower($validated['to_unit']);

        // Convert to grams first (base unit)
        $gramsValue = $this->convertToGrams($quantity, $fromUnit);
        
        // Convert from grams to target unit
        $convertedValue = $this->convertFromGrams($gramsValue, $toUnit);
        
        // Format the result based on the value
        $formattedValue = $this->formatConvertedValue($convertedValue);
        
        return response()->json([
            'original' => [
                'quantity' => $quantity,
                'unit' => $fromUnit
            ],
            'converted' => [
                'quantity' => $convertedValue,
                'formatted_quantity' => $formattedValue,
                'unit' => $toUnit
            ],
            'display_text' => $formattedValue . $toUnit
        ]);
    }

    /**
     * Convert any unit to grams (base unit)
     */
    private function convertToGrams(float $quantity, string $unit): float
    {
        $conversionRates = [
            'mg' => 0.001,      // mg to g: divide by 1000
            'g' => 1,           // g to g: no conversion
            'kg' => 1000,       // kg to g: multiply by 1000
            'lb' => 453.592,    // lb to g: multiply by 453.592
            'oz' => 28.3495,    // oz to g: multiply by 28.3495
            'ml' => 1,          // ml to g: 1:1 ratio (assuming water density)
        ];

        return $quantity * ($conversionRates[$unit] ?? 1);
    }

    /**
     * Convert grams to target unit
     */
    private function convertFromGrams(float $grams, string $unit): float
    {
        $conversionRates = [
            'mg' => 1000,       // g to mg: multiply by 1000
            'g' => 1,           // g to g: no conversion
            'kg' => 0.001,      // g to kg: divide by 1000
            'lb' => 0.00220462, // g to lb: divide by 453.592
            'oz' => 0.035274,   // g to oz: divide by 28.3495
            'ml' => 1,          // g to ml: 1:1 ratio (assuming water density)
        ];

        return $grams * ($conversionRates[$unit] ?? 1);
    }

    /**
     * Format converted value for display
     */
    private function formatConvertedValue(float $value): string
    {
        // If value is very small (< 0.01), show more decimal places
        if ($value < 0.01 && $value > 0) {
            return number_format($value, 4);
        }
        // If value is less than 1, show 2 decimal places
        elseif ($value < 1) {
            return number_format($value, 2);
        }
        // If value is a whole number, show no decimal places
        elseif ($value == floor($value)) {
            return number_format($value, 0);
        }
        // Otherwise, show 1 decimal place
        else {
            return number_format($value, 1);
        }
    }

    /**
     * Get smart unit suggestion based on quantity
     */
    public function suggestUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
            'current_unit' => 'required|string|in:mg,g,kg,lb,oz,ml'
        ]);

        $quantity = $validated['quantity'];
        $currentUnit = strtolower($validated['current_unit']);
        
        // Convert to grams for comparison
        $gramsValue = $this->convertToGrams($quantity, $currentUnit);
        
        $suggestedUnit = $currentUnit;
        $suggestedQuantity = $quantity;
        
        // Smart unit suggestions based on gram value
        if ($gramsValue >= 1000) {
            // If >= 1000g, suggest kg
            $suggestedUnit = 'kg';
            $suggestedQuantity = $this->convertFromGrams($gramsValue, 'kg');
        } elseif ($gramsValue < 1 && $gramsValue > 0) {
            // If < 1g, suggest mg
            $suggestedUnit = 'mg';
            $suggestedQuantity = $this->convertFromGrams($gramsValue, 'mg');
        } else {
            // Between 1g and 1000g, suggest g
            $suggestedUnit = 'g';
            $suggestedQuantity = $gramsValue;
        }
        
        $formattedQuantity = $this->formatConvertedValue($suggestedQuantity);
        
        return response()->json([
            'original' => [
                'quantity' => $quantity,
                'unit' => $currentUnit
            ],
            'suggested' => [
                'quantity' => $suggestedQuantity,
                'formatted_quantity' => $formattedQuantity,
                'unit' => $suggestedUnit
            ],
            'display_text' => $formattedQuantity . $suggestedUnit,
            'is_different' => $suggestedUnit !== $currentUnit
        ]);
    }

    /**
     * Get product metrics for selected products
     */
    public function getMetrics(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        
        $validated = $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id'
        ]);
        
        $productIds = $validated['product_ids'];
        
        // Verify user owns these products
        $userProducts = $user->products()->whereIn('id', $productIds)->pluck('id')->toArray();
        if (count($userProducts) !== count($productIds)) {
            return response()->json([
                'message' => 'Some products do not belong to the authenticated user'
            ], 403);
        }
        
        try {
            // Get total ingredients count
            $totalIngredients = $user->products()
                ->whereIn('id', $productIds)
                ->withCount('ingredients')
                ->get()
                ->sum('ingredients_count');
            
            // Get auto-tagged products count (products with nutrition data that have health_labels)
            $autoTaggedCount = $user->products()
                ->whereIn('id', $productIds)
                ->whereHas('nutritionAutoTags', function($query) {
                    $query->whereNotNull('health_labels')
                          ->where('health_labels', '!=', '[]')
                          ->where('health_labels', '!=', 'null');
                })
                ->count();
            
            // Get products with notes count
            $withNotesCount = $user->products()
                ->whereIn('id', $productIds)
                ->whereNotNull('ingredient_notes')
                ->where('ingredient_notes', '!=', '')
                ->count();
            
            // Get allergens found count (products with nutrition data that have allergens)
            $allergensFoundCount = $user->products()
                ->whereIn('id', $productIds)
                ->whereHas('nutritionAutoTags', function($query) {
                    $query->whereNotNull('allergens')
                          ->where('allergens', '!=', '[]')
                          ->where('allergens', '!=', 'null');
                })
                ->count();
            
            $metrics = [
                'total_ingredients' => $totalIngredients,
                'auto_tagged' => $autoTaggedCount,
                'with_notes' => $withNotesCount,
                'allergens_found' => $allergensFoundCount
            ];
            
            Log::info('Product metrics calculated', [
                'user_id' => $user->id,
                'product_ids' => $productIds,
                'metrics' => $metrics
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to calculate product metrics', [
                'user_id' => $user->id,
                'product_ids' => $productIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate product metrics'
            ], 500);
        }
    }

    /**
     * Extract and validate image URL from various sources (including Google search results)
     */
    public function extractImageUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2048'
        ]);

        $url = $validated['url'];
        $result = [
            'original_url' => $url,
            'is_google_url' => false,
            'extracted_url' => null,
            'is_valid_image' => false,
            'message' => '',
            'error' => null
        ];

        try {
            // Check if it's a Google search result URL
            if ($this->isGoogleSearchUrl($url)) {
                $result['is_google_url'] = true;
                $extractedUrl = $this->extractImageUrlFromGoogle($url);
                
                if ($extractedUrl) {
                    $result['extracted_url'] = $extractedUrl;
                    $result['is_valid_image'] = $this->isValidImageUrl($extractedUrl);
                    
                    if ($result['is_valid_image']) {
                        $result['message'] = 'Google search URL detected. Successfully extracted direct image URL.';
                    } else {
                        $result['message'] = 'Google search URL detected, but extracted URL may not be a valid image.';
                    }
                } else {
                    $result['message'] = 'Google search URL detected, but could not extract direct image URL. Please copy the image address directly.';
                }
            } else {
                // Direct URL validation
                $result['is_valid_image'] = $this->isValidImageUrl($url);
                $result['extracted_url'] = $url;
                
                if ($result['is_valid_image']) {
                    $result['message'] = 'Valid image URL detected.';
                } else {
                    $result['message'] = 'URL provided, but it may not be a direct image URL.';
                }
            }
        } catch (\Exception $e) {
            $result['error'] = 'Failed to process URL: ' . $e->getMessage();
            Log::error('Image URL extraction failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json($result);
    }

    /**
     * Check if URL is a Google search result URL
     */
    private function isGoogleSearchUrl(string $url): bool
    {
        return strpos($url, 'google.com') !== false && 
               (strpos($url, '/imgres?') !== false || strpos($url, '/url?') !== false);
    }

    /**
     * Extract direct image URL from Google search result URL
     */
    private function extractImageUrlFromGoogle(string $url): ?string
    {
        // Parse the URL to get query parameters
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['query'])) {
            return null;
        }

        parse_str($parsedUrl['query'], $queryParams);
        
        // Try to get the image URL from different Google URL formats
        if (isset($queryParams['imgurl'])) {
            return urldecode($queryParams['imgurl']);
        }
        
        if (isset($queryParams['url'])) {
            return urldecode($queryParams['url']);
        }
        
        // Try to extract from imgres format
        if (strpos($url, 'imgurl=') !== false) {
            preg_match('/imgurl=([^&]+)/', $url, $matches);
            if (isset($matches[1])) {
                return urldecode($matches[1]);
            }
        }
        
        return null;
    }

    /**
     * Validate if URL is likely a direct image URL
     */
    private function isValidImageUrl(string $url): bool
    {
        // Check file extension
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
            if (in_array($extension, $validExtensions)) {
                return true;
            }
        }
        
        // Check for common image hosting domains
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            $imageHosts = [
                'images.unsplash.com',
                'cdn.pixabay.com',
                'images.pexels.com',
                'i.imgur.com',
                'cdn.shopify.com',
                'images-na.ssl-images-amazon.com',
                'target.scene7.com',
                'walmart.com',
                'costco.com'
            ];
            
            foreach ($imageHosts as $imageHost) {
                if (strpos($host, $imageHost) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
