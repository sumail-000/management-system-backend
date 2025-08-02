<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class IngredientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ingredient::query();

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by allergens
        if ($request->has('allergens')) {
            $allergens = is_array($request->allergens) ? $request->allergens : [$request->allergens];
            $query->where(function ($q) use ($allergens) {
                foreach ($allergens as $allergen) {
                    $q->orWhereJsonContains('allergens', $allergen);
                }
            });
        }

        // Filter by tags
        if ($request->has('tags')) {
            $tags = is_array($request->tags) ? $request->tags : [$request->tags];
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }

        $ingredients = $query->paginate($request->get('per_page', 15));

        return response()->json($ingredients);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ingredients,name',
            'description' => 'nullable|string',
            'edamam_id' => 'nullable|string|max:255|unique:ingredients,edamam_id',
            'allergens' => 'nullable|array',
            'allergens.*' => 'string|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'notes' => 'nullable|string',
        ]);

        $ingredient = Ingredient::create($validated);

        return response()->json($ingredient, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $ingredient = Ingredient::with('products')->findOrFail($id);

        return response()->json($ingredient);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $ingredient = Ingredient::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:ingredients,name,' . $id,
            'description' => 'nullable|string',
            'edamam_id' => 'nullable|string|max:255|unique:ingredients,edamam_id,' . $id,
            'allergens' => 'nullable|array',
            'allergens.*' => 'string|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'notes' => 'nullable|string',
        ]);

        $ingredient->update($validated);

        return response()->json($ingredient);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $ingredient = Ingredient::findOrFail($id);
        
        // Check if ingredient is used in any products
        if ($ingredient->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete ingredient as it is used in one or more products'
            ], 422);
        }
        
        $ingredient->delete();

        return response()->json(['message' => 'Ingredient deleted successfully']);
    }

    /**
     * Search ingredients by name for autocomplete
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100'
        ]);

        $ingredients = Ingredient::where('name', 'like', '%' . $request->q . '%')
            ->select('id', 'name', 'allergens', 'tags')
            ->limit(20)
            ->get();

        return response()->json($ingredients);
    }
}
