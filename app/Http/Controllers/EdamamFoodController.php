<?php

namespace App\Http\Controllers;

use App\Http\Requests\EdamamFoodSearchRequest;
use App\Http\Resources\EdamamFoodResource;
use App\Http\Resources\EdamamFoodCollection;
use App\Http\Resources\EdamamErrorResource;
use App\Services\EdamamFoodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EdamamFoodController extends Controller
{
    protected EdamamFoodService $foodService;
    
    public function __construct(EdamamFoodService $foodService)
    {
        $this->foodService = $foodService;
    }
    
    /**
     * Get ingredient autocomplete suggestions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function autocomplete(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:1|max:100',
                'limit' => 'sometimes|integer|min:1|max:20'
            ]);
            
            $query = $request->input('q');
            $limit = $request->input('limit', 10);
            
            // Generate cache key for autocomplete
            $cacheKey = 'food_autocomplete_' . md5($query . '_' . $limit);
            
            // Check cache first (cache for 30 minutes)
            $result = Cache::remember($cacheKey, 1800, function () use ($query, $limit) {
                return $this->foodService->autocomplete($query, $limit);
            });
            
            if (isset($result['error'])) {
                return response()->json(
                    EdamamErrorResource::fromApiResponse($result),
                    $result['status'] ?? 400
                );
            }
            
            return response()->json([
                'success' => true,
                'data' => new EdamamFoodResource($result),
                'meta' => [
                    'query' => $query,
                    'limit' => $limit,
                    'cached' => Cache::has($cacheKey),
                    'timestamp' => now()->toISOString(),
                    'request_id' => $request->header('X-Request-ID', uniqid())
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Food autocomplete failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $request->input('q')
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
    
    /**
     * Search food database
     *
     * @param EdamamFoodSearchRequest $request
     * @return JsonResponse
     */
    public function search(EdamamFoodSearchRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Generate cache key for food search
            $cacheKey = 'food_search_' . md5(json_encode($data));
            
            // Check cache first (cache for 1 hour)
            $result = Cache::remember($cacheKey, 3600, function () use ($data) {
                return $this->foodService->searchFood($data['q'] ?? '', $data);
            });
            
            if (isset($result['error'])) {
                return response()->json(
                    EdamamErrorResource::fromApiResponse($result),
                    $result['status'] ?? 400
                );
            }
            
            return response()->json([
                'success' => true,
                'data' => new EdamamFoodResource($result),
                'meta' => [
                    'search_params' => $data,
                    'cached' => Cache::has($cacheKey),
                    'timestamp' => now()->toISOString(),
                    'request_id' => $request->header('X-Request-ID', uniqid())
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Food search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
    
    /**
     * Get food details by UPC code
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getByUpc(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'upc' => 'required|string|regex:/^[0-9]{8,14}$/'
            ]);
            
            $upc = $request->input('upc');
            
            // Generate cache key for UPC lookup
            $cacheKey = 'food_upc_' . $upc;
            
            // Check cache first (cache for 24 hours)
            $result = Cache::remember($cacheKey, 86400, function () use ($upc) {
                return $this->foodService->searchFood('', ['upc' => $upc]);
            });
            
            if (isset($result['error'])) {
                return response()->json(
                    EdamamErrorResource::fromApiResponse($result),
                    $result['status'] ?? 400
                );
            }
            
            return response()->json([
                'success' => true,
                'data' => new EdamamFoodResource($result),
                'meta' => [
                    'upc' => $upc,
                    'cached' => Cache::has($cacheKey),
                    'timestamp' => now()->toISOString(),
                    'request_id' => $request->header('X-Request-ID', uniqid())
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('UPC food lookup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'upc' => $request->input('upc')
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
    
    /**
     * Get popular food suggestions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function popular(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'category' => 'sometimes|string|in:fruits,vegetables,proteins,grains,dairy,beverages',
                'limit' => 'sometimes|integer|min:1|max:50'
            ]);
            
            $category = $request->input('category');
            $limit = $request->input('limit', 20);
            
            // Define popular foods by category
            $popularFoods = [
                'fruits' => ['apple', 'banana', 'orange', 'strawberry', 'grape', 'pineapple', 'mango', 'kiwi'],
                'vegetables' => ['broccoli', 'carrot', 'spinach', 'tomato', 'cucumber', 'bell pepper', 'onion', 'garlic'],
                'proteins' => ['chicken breast', 'salmon', 'eggs', 'tofu', 'beef', 'turkey', 'tuna', 'beans'],
                'grains' => ['rice', 'quinoa', 'oats', 'bread', 'pasta', 'barley', 'wheat', 'corn'],
                'dairy' => ['milk', 'cheese', 'yogurt', 'butter', 'cream', 'cottage cheese'],
                'beverages' => ['water', 'coffee', 'tea', 'juice', 'soda', 'milk']
            ];
            
            $foods = $category ? ($popularFoods[$category] ?? []) : collect($popularFoods)->flatten()->toArray();
            $foods = array_slice($foods, 0, $limit);
            
            $suggestions = [];
            foreach ($foods as $food) {
                $suggestions[] = [
                    'text' => $food,
                    'category' => $category ?: 'general',
                    'popularity_score' => rand(70, 100)
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'suggestions' => $suggestions,
                    'total' => count($suggestions)
                ],
                'meta' => [
                    'category' => $category,
                    'limit' => $limit,
                    'timestamp' => now()->toISOString()
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Popular foods retrieval failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
    
    /**
     * Get food categories
     *
     * @return JsonResponse
     */
    public function categories(): JsonResponse
    {
        try {
            $categories = [
                'generic-foods' => 'Generic Foods',
                'packaged-foods' => 'Packaged Foods',
                'generic-meals' => 'Generic Meals',
                'fast-foods' => 'Fast Foods'
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'categories' => $categories,
                    'total' => count($categories)
                ],
                'meta' => [
                    'timestamp' => now()->toISOString()
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Food categories retrieval failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
    
    /**
     * Clear food search cache
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'sometimes|string|in:autocomplete,search,upc,all'
            ]);
            
            $type = $request->input('type', 'all');
            $patterns = [
                'autocomplete' => 'food_autocomplete_*',
                'search' => 'food_search_*',
                'upc' => 'food_upc_*',
                'all' => 'food_*'
            ];
            
            $pattern = $patterns[$type];
            
            // Clear cache entries matching pattern
            $cleared = 0;
            $cacheKeys = Cache::getRedis()->keys($pattern);
            
            foreach ($cacheKeys as $key) {
                if (Cache::forget($key)) {
                    $cleared++;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Cleared {$cleared} food cache entries",
                'meta' => [
                    'type' => $type,
                    'pattern' => $pattern,
                    'cleared_count' => $cleared,
                    'timestamp' => now()->toISOString()
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Food cache clearing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(
                EdamamErrorResource::fromException($e),
                500
            );
        }
    }
}