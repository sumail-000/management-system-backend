<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\RecentActivity;
use App\Models\Notification;

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

        // Filter by status: published | draft | public | flagged
        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if (in_array($status, ['published', 'draft'])) {
                $query->where('status', $status);
            } elseif ($status === 'public') {
                $query->where('is_public', true);
            } elseif (in_array($status, ['flag', 'flagged'])) {
                $query->where('is_flagged', true);
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
        $allowedSorts = ['created_at', 'updated_at', 'name', 'status', 'is_public', 'is_flagged'];
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
        $base = Product::query()->whereNull('deleted_at');
        $total = (clone $base)->count();
        $flagged = (clone $base)->where('is_flagged', true)->count();
        // Exclude flagged from other buckets per business rule
        $nonFlagged = (clone $base)->where('is_flagged', false);
        $public = (clone $nonFlagged)->where('is_public', true)->count();
        $published = (clone $nonFlagged)->where('status', 'published')->count();
        $draft = (clone $nonFlagged)->where('status', 'draft')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'public' => $public,
                'published' => $published,
                'draft' => $draft,
                'flagged' => $flagged,
            ]
        ]);
    }

    /**
     * Toggle flagged status for a product
     */
    public function toggleFlag(int $id): JsonResponse
    {
        $product = Product::query()->findOrFail($id);
        $product->is_flagged = !$product->is_flagged;
        // Business rule: flagged products are not public
        if ($product->is_flagged) {
            $product->is_public = false;
        }
        $product->save();

        // Log recent activity and notify product owner on flag
        try {
            if ($product->is_flagged) {
                RecentActivity::logProductFlagged($product);
                // Create user-friendly notification for the product owner
                Notification::create([
                    'user_id' => $product->user_id,
                    'type' => 'product_flagged',
                    'title' => 'Your product was flagged',
                    'message' => 'Your product "' . $product->name . '" was flagged by our moderation team. If you believe this is a mistake, please open a support ticket and reference this product.',
                    'metadata' => [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'flagged_at' => now()->toISOString(),
                    ],
                    'link' => '/support?ref=product_flagged&product_id=' . $product->id,
                ]);
            }
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'is_flagged' => $product->is_flagged,
                'is_public' => $product->is_public,
                'status' => $product->status,
            ],
            'message' => $product->is_flagged ? 'Product flagged' : 'Product unflagged',
        ]);
    }

    /**
     * Show full product details for admin
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['user.membershipPlan', 'category'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Permanently delete a product
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Product permanently deleted',
        ]);
    }
}
