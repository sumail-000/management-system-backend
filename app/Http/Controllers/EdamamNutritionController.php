<?php

namespace App\Http\Controllers;

use App\Http\Requests\EdamamNutritionAnalysisRequest;
use App\Http\Resources\EdamamNutritionResource;
use App\Http\Resources\EdamamNutritionCollection;
use App\Http\Resources\EdamamErrorResource;
use App\Services\EdamamNutritionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EdamamNutritionController extends Controller
{
    protected EdamamNutritionService $nutritionService;
    
    public function __construct(EdamamNutritionService $nutritionService)
    {
        $this->nutritionService = $nutritionService;
    }
    
    /**
     * Analyze nutrition for ingredients
     *
     * @param EdamamNutritionAnalysisRequest $request
     * @return JsonResponse
     */
    public function analyze(EdamamNutritionAnalysisRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Generate cache key for nutrition analysis
            $cacheKey = 'nutrition_analysis_' . md5(json_encode($data));
            
            // Check cache first (cache for 1 hour)
            $result = Cache::remember($cacheKey, 3600, function () use ($data) {
                return $this->nutritionService->analyzeNutrition($data);
            });
            
            if (isset($result['error'])) {
                return response()->json(
                    EdamamErrorResource::fromApiResponse($result),
                    $result['status'] ?? 400
                );
            }
            
            return response()->json([
                'success' => true,
                'data' => new EdamamNutritionResource($result),
                'meta' => [
                    'cached' => Cache::has($cacheKey),
                    'timestamp' => now()->toISOString(),
                    'request_id' => $request->header('X-Request-ID', uniqid())
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Nutrition analysis failed', [
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
     * Batch analyze nutrition for multiple ingredient sets
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function batchAnalyze(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'analyses' => 'required|array|min:1|max:10',
                'analyses.*.ingr' => 'required|array|min:1|max:100',
                'analyses.*.ingr.*' => 'required|string|max:500',
                'analyses.*.nutrition-type' => 'sometimes|in:cooking,logging',
                'analyses.*.meal-type' => 'sometimes|in:breakfast,lunch,dinner,snack',
                'analyses.*.dish-type' => 'sometimes|in:main course,side dish,dessert,appetizer,salad,bread,breakfast,soup,beverage,sauce,marinade,fingerfood,condiment,dressing,drink'
            ]);
            
            $analyses = $request->input('analyses');
            $results = [];
            
            foreach ($analyses as $index => $analysisData) {
                try {
                    $cacheKey = 'nutrition_analysis_' . md5(json_encode($analysisData));
                    
                    $result = Cache::remember($cacheKey, 3600, function () use ($analysisData) {
                        return $this->nutritionService->analyzeNutrition($analysisData);
                    });
                    
                    if (isset($result['error'])) {
                        $results[] = [
                            'index' => $index,
                            'success' => false,
                            'error' => EdamamErrorResource::fromApiResponse($result)
                        ];
                    } else {
                        $results[] = [
                            'index' => $index,
                            'success' => true,
                            'data' => new EdamamNutritionResource($result)
                        ];
                    }
                } catch (Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => EdamamErrorResource::fromException($e)
                    ];
                }
            }
            
            // Filter successful results for collection
            $successfulResults = collect($results)
                ->where('success', true)
                ->pluck('data')
                ->toArray();
            
            return response()->json([
                'success' => true,
                'data' => new EdamamNutritionCollection($successfulResults),
                'results' => $results,
                'meta' => [
                    'total_analyses' => count($analyses),
                    'successful' => count($successfulResults),
                    'failed' => count($analyses) - count($successfulResults),
                    'timestamp' => now()->toISOString(),
                    'request_id' => $request->header('X-Request-ID', uniqid())
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Batch nutrition analysis failed', [
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
     * Get nutrition analysis history
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'sometimes|integer|min:1|max:100',
                'offset' => 'sometimes|integer|min:0',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from'
            ]);
            
            // This would typically fetch from a database
            // For now, return empty collection with proper structure
            return response()->json([
                'success' => true,
                'data' => new EdamamNutritionCollection([]),
                'meta' => [
                    'total' => 0,
                    'limit' => $request->input('limit', 20),
                    'offset' => $request->input('offset', 0),
                    'timestamp' => now()->toISOString()
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Nutrition history retrieval failed', [
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
     * Clear nutrition analysis cache
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'pattern' => 'sometimes|string|max:100'
            ]);
            
            $pattern = $request->input('pattern', 'nutrition_analysis_*');
            
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
                'message' => "Cleared {$cleared} cache entries",
                'meta' => [
                    'pattern' => $pattern,
                    'cleared_count' => $cleared,
                    'timestamp' => now()->toISOString()
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Cache clearing failed', [
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