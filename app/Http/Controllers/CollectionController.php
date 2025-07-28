<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    /**
     * Get all collections for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $collections = Collection::forUser(Auth::id())
            ->withCount('products')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($collections);
    }

    /**
     * Create a new collection.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/'
        ]);

        $collection = Collection::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#3b82f6'
        ]);

        $collection->loadCount('products');

        return response()->json($collection, 201);
    }

    /**
     * Get a specific collection with its products.
     */
    public function show(Collection $collection): JsonResponse
    {
        // Ensure the collection belongs to the authenticated user
        if ($collection->user_id !== Auth::id()) {
            return response()->json(['message' => 'Collection not found'], 404);
        }

        $collection->load(['products.category', 'products.ingredients'])
                   ->loadCount('products');

        return response()->json($collection);
    }

    /**
     * Update a collection.
     */
    public function update(Request $request, Collection $collection): JsonResponse
    {
        // Ensure the collection belongs to the authenticated user
        if ($collection->user_id !== Auth::id()) {
            return response()->json(['message' => 'Collection not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/'
        ]);

        $collection->update($validated);
        $collection->loadCount('products');

        return response()->json($collection);
    }

    /**
     * Delete a collection.
     */
    public function destroy(Collection $collection): JsonResponse
    {
        // Ensure the collection belongs to the authenticated user
        if ($collection->user_id !== Auth::id()) {
            return response()->json(['message' => 'Collection not found'], 404);
        }

        $collection->delete();

        return response()->json(['message' => 'Collection deleted successfully']);
    }

    /**
     * Add a product to a collection.
     */
    public function addProduct(Request $request, Collection $collection): JsonResponse
    {
        // Ensure the collection belongs to the authenticated user
        if ($collection->user_id !== Auth::id()) {
            return response()->json(['message' => 'Collection not found'], 404);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $product = Product::find($validated['product_id']);

        // Ensure the product belongs to the authenticated user
        if ($product->user_id !== Auth::id()) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Check if product is already in collection
        if ($collection->products()->where('product_id', $product->id)->exists()) {
            return response()->json(['message' => 'Product already in collection'], 409);
        }

        $collection->products()->attach($product->id);
        $collection->loadCount('products');

        return response()->json([
            'message' => 'Product added to collection successfully',
            'collection' => $collection
        ]);
    }

    /**
     * Remove a product from a collection.
     */
    public function removeProduct(Collection $collection, Product $product): JsonResponse
    {
        // Ensure the collection belongs to the authenticated user
        if ($collection->user_id !== Auth::id()) {
            return response()->json(['message' => 'Collection not found'], 404);
        }

        // Ensure the product belongs to the authenticated user
        if ($product->user_id !== Auth::id()) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $collection->products()->detach($product->id);
        $collection->loadCount('products');

        return response()->json([
            'message' => 'Product removed from collection successfully',
            'collection' => $collection
        ]);
    }

    /**
     * Get products in a collection with pagination.
     */
    public function getProducts(Request $request, Collection $collection): JsonResponse
    {
        // Ensure the collection belongs to the authenticated user
        if ($collection->user_id !== Auth::id()) {
            return response()->json(['message' => 'Collection not found'], 404);
        }

        $perPage = $request->get('per_page', 12);
        $search = $request->get('search');
        $categoryId = $request->get('category_id');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query = $collection->products()
            ->with(['category', 'ingredients']);

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $validSortColumns = ['name', 'created_at', 'updated_at'];
        if (in_array($sortBy, $validSortColumns)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $products = $query->paginate($perPage);

        return response()->json($products);
    }
}