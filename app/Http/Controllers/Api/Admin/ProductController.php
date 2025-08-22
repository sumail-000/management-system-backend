<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with(['user:id,name', 'category:id,name']);

        // Apply search by name
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->string('search') . '%';
            $query->where('name', 'like', $searchTerm);
        }

        // Filter by status: published | draft | public
        if ($request->filled('status')) {
            $status = $request->string('status');
            if (in_array($status, ['published', 'draft'])) {
                $query->where('status', $status);
            } elseif ($status === 'public') {
                $query->where('is_public', true);
            }
        }

        // Filter by category name (partial match)
        if ($request->filled('category')) {
            $category = '%' . $request->string('category') . '%';
            $query->whereHas('category', function ($q) use ($category) {
                $q->where('name', 'like', $category);
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['created_at', 'updated_at', 'name', 'status', 'is_public'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');

        // Paginate results
        $perPage = (int) $request->input('per_page', 10);
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
    }

    /**
     * Get products metrics for admin overview
     */
    public function metrics(Request $request): JsonResponse
    {
        $total = Product::query()->whereNull('deleted_at')->count();
        $public = Product::query()->where('is_public', true)->whereNull('deleted_at')->count();
        $published = Product::query()->where('status', 'published')->whereNull('deleted_at')->count();
        $draft = Product::query()->where('status', 'draft')->whereNull('deleted_at')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'public' => $public,
                'published' => $published,
                'draft' => $draft,
                // 'flagged' => 0 // reserved for future moderation flagging feature
            ]
        ]);
    }
}
