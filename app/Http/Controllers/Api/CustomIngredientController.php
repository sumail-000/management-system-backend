<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomIngredient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CustomIngredientController extends Controller
{
    /**
     * Display a listing of the user's custom ingredients.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CustomIngredient::forUser(Auth::id())
                ->active()
                ->orderBy('name');

            // Apply search filter
            if ($request->has('search') && $request->search) {
                $query->search($request->search);
            }

            // Apply category filter
            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }

            // Paginate results
            $perPage = $request->get('per_page', 15);
            $ingredients = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $ingredients->items(),
                'pagination' => [
                    'current_page' => $ingredients->currentPage(),
                    'last_page' => $ingredients->lastPage(),
                    'per_page' => $ingredients->perPage(),
                    'total' => $ingredients->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching custom ingredients: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch custom ingredients'
            ], 500);
        }
    }

    /**
     * Store a newly created custom ingredient.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('Creating custom ingredient', [
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            // Minimal validation - only require name as requested
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'brand' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:2000',
                'ingredient_list' => 'nullable|string|max:5000',
                'serving_size' => 'nullable|numeric|min:0',
                'serving_unit' => 'nullable|string|max:20',
                'nutrition_data' => 'nullable|array',
                'vitamins_minerals' => 'nullable|array',
                'additional_nutrients' => 'nullable|array',
                'allergens_data' => 'nullable|array',
                'nutrition_notes' => 'nullable|string|max:2000',
                'is_public' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create the custom ingredient
            $ingredient = CustomIngredient::create([
                'user_id' => Auth::id(),
                'name' => $request->name,
                'brand' => $request->brand,
                'category' => $request->category,
                'description' => $request->description,
                'ingredient_list' => $request->ingredient_list,
                'serving_size' => $request->serving_size ?? 100,
                'serving_unit' => $request->serving_unit ?? 'g',
                'nutrition_data' => $request->nutrition_data,
                'vitamins_minerals' => $request->vitamins_minerals,
                'additional_nutrients' => $request->additional_nutrients,
                'allergens_data' => $request->allergens_data,
                'nutrition_notes' => $request->nutrition_notes,
                'is_public' => $request->get('is_public', false),
                'status' => 'active',
            ]);

            Log::info('Custom ingredient created successfully', [
                'ingredient_id' => $ingredient->id,
                'user_id' => Auth::id(),
                'name' => $ingredient->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Custom ingredient created successfully',
                'data' => $ingredient
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating custom ingredient', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create custom ingredient: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified custom ingredient.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $ingredient = CustomIngredient::forUser(Auth::id())
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $ingredient
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Custom ingredient not found'
            ], 404);
        }
    }

    /**
     * Update the specified custom ingredient.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $ingredient = CustomIngredient::forUser(Auth::id())->findOrFail($id);

            // Minimal validation - only require name as requested
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'brand' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:2000',
                'ingredient_list' => 'nullable|string|max:5000',
                'serving_size' => 'nullable|numeric|min:0',
                'serving_unit' => 'nullable|string|max:20',
                'nutrition_data' => 'nullable|array',
                'vitamins_minerals' => 'nullable|array',
                'additional_nutrients' => 'nullable|array',
                'allergens_data' => 'nullable|array',
                'nutrition_notes' => 'nullable|string|max:2000',
                'is_public' => 'boolean',
                'status' => 'in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $ingredient->update($request->only([
                'name', 'brand', 'category', 'description', 'ingredient_list',
                'serving_size', 'serving_unit', 'nutrition_data', 'vitamins_minerals',
                'additional_nutrients', 'allergens_data', 'nutrition_notes',
                'is_public', 'status'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Custom ingredient updated successfully',
                'data' => $ingredient->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating custom ingredient: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update custom ingredient'
            ], 500);
        }
    }

    /**
     * Remove the specified custom ingredient.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $ingredient = CustomIngredient::forUser(Auth::id())->findOrFail($id);
            $ingredient->delete();

            return response()->json([
                'success' => true,
                'message' => 'Custom ingredient deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting custom ingredient: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete custom ingredient'
            ], 500);
        }
    }

    /**
     * Search custom ingredients (for recipe ingredient search)
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->get('query', '');
            
            if (empty($query)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            // Search user's own ingredients and public ingredients
            $ingredients = CustomIngredient::where(function ($q) {
                $q->forUser(Auth::id())
                  ->orWhere('is_public', true);
            })
            ->active()
            ->search($query)
            ->orderBy('usage_count', 'desc') // Most used first
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'brand', 'category', 'serving_size', 'serving_unit', 'nutrition_data', 'allergens_data']);

            // Format for recipe search compatibility
            $formattedIngredients = $ingredients->map(function ($ingredient) {
                return [
                    'id' => 'custom-' . $ingredient->id,
                    'name' => $ingredient->name . ($ingredient->brand ? " ({$ingredient->brand})" : ''),
                    'category' => $ingredient->category ?? 'Custom',
                    'type' => 'custom',
                    'custom_ingredient_id' => $ingredient->id,
                    'serving_size' => $ingredient->serving_size,
                    'serving_unit' => $ingredient->serving_unit,
                    'nutrition_data' => $ingredient->nutrition_data,
                    'allergens' => $ingredient->allergens_list ?? [],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedIngredients
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching custom ingredients: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to search custom ingredients'
            ], 500);
        }
    }

    /**
     * Get categories of custom ingredients for the user
     */
    public function getCategories(): JsonResponse
    {
        try {
            $categories = CustomIngredient::forUser(Auth::id())
                ->active()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->pluck('category')
                ->sort()
                ->values();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching custom ingredient categories: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories'
            ], 500);
        }
    }

    /**
     * Get recipe usage information for a custom ingredient
     */
    public function getUsage(string $id): JsonResponse
    {
        try {
            $ingredient = CustomIngredient::forUser(Auth::id())->findOrFail($id);
            
            // Get recipes that use this custom ingredient
            // This would require checking the ingredients_data JSON field in products table
            // For now, we'll return the usage_count from the ingredient itself
            
            return response()->json([
                'success' => true,
                'data' => [
                    'ingredient_id' => $ingredient->id,
                    'ingredient_name' => $ingredient->name,
                    'usage_count' => $ingredient->usage_count ?? 0,
                    'recipes' => [] // TODO: Implement recipe lookup
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching ingredient usage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ingredient usage'
            ], 500);
        }
    }

    /**
     * Increment usage count when ingredient is used in a recipe
     */
    public function incrementUsage(string $id): JsonResponse
    {
        try {
            $ingredient = CustomIngredient::forUser(Auth::id())->findOrFail($id);
            $ingredient->incrementUsage();

            return response()->json([
                'success' => true,
                'message' => 'Usage count incremented successfully',
                'data' => [
                    'ingredient_id' => $ingredient->id,
                    'usage_count' => $ingredient->usage_count
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error incrementing ingredient usage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to increment ingredient usage'
            ], 500);
        }
    }
}