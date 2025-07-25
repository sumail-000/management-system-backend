<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\Ingredient;
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
        $query = $user->products()->with(['ingredients', 'nutritionalData', 'category']);

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
                'serving_size' => 'required|numeric|min:0',
                'serving_unit' => 'required|string|max:50',
                'servings_per_container' => 'required|integer|min:1',
                'is_public' => 'boolean',
                'is_pinned' => 'boolean',
                'status' => 'in:draft,published',
                'image_url' => 'nullable|url|max:2048',
                'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
                'ingredients' => 'nullable|array',
                'ingredients.*.id' => 'nullable|string',
                'ingredients.*.name' => 'required|string|max:255',
                'ingredients.*.quantity' => 'nullable|numeric|min:0',
                'ingredients.*.unit' => 'nullable|string|max:50',
                'ingredient_notes' => 'nullable|string|max:2000',
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
        
        // Extract ingredients data before creating product
        $ingredientsData = $validated['ingredients'] ?? [];
        unset($validated['ingredients']);

        $product = Product::create($validated);
        
        // Handle ingredients
        if (!empty($ingredientsData)) {
            $this->syncProductIngredients($product, $ingredientsData);
        }
        
        $product->load(['ingredients', 'nutritionalData', 'category']);

        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $product = Product::with(['ingredients', 'nutritionalData', 'labels', 'user:id,name,email', 'category'])
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
            'serving_size' => 'sometimes|required|numeric|min:0',
            'serving_unit' => 'sometimes|required|string|max:50',
            'servings_per_container' => 'sometimes|required|integer|min:1',
            'is_public' => 'boolean',
            'is_pinned' => 'boolean',
            'status' => 'in:draft,published',
            'image_url' => 'nullable|url|max:2048',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'ingredients' => 'nullable|array',
            'ingredients.*.id' => 'nullable|string',
            'ingredients.*.name' => 'required|string|max:255',
            'ingredients.*.quantity' => 'nullable|numeric|min:0',
            'ingredients.*.unit' => 'nullable|string|max:50',
            'ingredient_notes' => 'nullable|string|max:2000',
        ]);

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
        
        // Extract ingredients data before updating product
        $ingredientsData = $validated['ingredients'] ?? null;
        unset($validated['ingredients']);

        $product->update($validated);
        
        // Handle ingredients if provided
        if ($ingredientsData !== null) {
            $this->syncProductIngredients($product, $ingredientsData);
        }
        
        $product->load(['ingredients', 'nutritionalData', 'category']);

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
    private function syncProductIngredients(Product $product, array $ingredientsData): void
    {
        $syncData = [];
        
        foreach ($ingredientsData as $index => $ingredientData) {
            $ingredientName = trim($ingredientData['name']);
            
            if (empty($ingredientName)) {
                continue;
            }
            
            // Find or create ingredient
            $ingredient = Ingredient::firstOrCreate(
                ['name' => $ingredientName],
                ['description' => null]
            );
            
            // Add to sync data with order, amount (quantity), and unit
            $syncData[$ingredient->id] = [
                'order' => $index + 1,
                'amount' => isset($ingredientData['quantity']) ? (float)$ingredientData['quantity'] : null,
                'unit' => $ingredientData['unit'] ?? null
            ];
        }
        
        // Sync ingredients with the product
        $product->ingredients()->sync($syncData);
    }

    /**
     * Get public products
     */
    public function public(Request $request): JsonResponse
    {
        $query = Product::with(['ingredients', 'nutritionalData', 'user:id,name,company', 'category'])
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
        $product = Product::with(['ingredients', 'nutritionalData', 'user:id,name,company', 'category'])
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
        
        $duplicatedProduct->load(['ingredients', 'nutritionalData', 'category']);
        
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
        $query = $user->products()->onlyTrashed()->with(['ingredients', 'nutritionalData', 'category']);
        
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
}
