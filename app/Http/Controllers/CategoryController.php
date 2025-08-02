<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class CategoryController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get all categories for the authenticated user.
     */
    public function index(): JsonResponse
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
     * Store a new category for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $validator = Validator::make($request->all(), 
                Category::validationRules($user->id)
            );

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = Category::create([
                'name' => $request->name,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Category created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating category: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category'
            ], 500);
        }
    }

    /**
     * Update an existing category for the authenticated user.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $category = Category::forUser($user->id)->find($id);
            
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), 
                Category::validationRules($user->id, $id)
            );

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category->update([
                'name' => $request->name
            ]);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Category updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating category: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category'
            ], 500);
        }
    }

    /**
     * Delete a category.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $category = Category::forUser($user->id)->findOrFail($id);
            
            // Check if category has associated products
            $productCount = $category->products()->count();
            if ($productCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete category '{$category->name}' because it is being used by {$productCount} product(s). Please reassign or delete the products first.",
                    'error_type' => 'category_in_use',
                    'products_count' => $productCount
                ], 422);
            }
            
            $category->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting category: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category'
            ], 500);
        }
    }



    /**
     * Search categories for the authenticated user.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = $request->get('query', '');
            
            $categoriesQuery = Category::forUser($user->id)
                ->withCount('products')
                ->orderBy('name');
            
            // If query is provided, filter by name using case-insensitive partial matching
            if (!empty(trim($query))) {
                $categoriesQuery->where('name', 'LIKE', '%' . trim($query) . '%');
            }
            
            $categories = $categoriesQuery->get(['id', 'name', 'created_at']);

            return response()->json([
                'success' => true,
                'data' => $categories,
                'message' => 'Categories searched successfully',
                'query' => $query
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching categories: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to search categories'
            ], 500);
        }
    }

    /**
     * Get a specific category for the authenticated user.
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $category = Category::forUser($user->id)->find($id);
            
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Category retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching category: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve category'
            ], 500);
        }
    }
}
