<?php

namespace App\Http\Controllers;

use App\Http\Requests\EdamamNutritionAnalysisRequest;
use App\Http\Resources\EdamamNutritionResource;
use App\Http\Resources\EdamamNutritionCollection;
use App\Http\Resources\EdamamErrorResource;
use App\Services\EdamamNutritionService;
use App\Services\NutritionDataTransformationService;
use App\Models\NutritionalData;
use App\Models\Product;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EdamamNutritionController extends Controller
{
    protected EdamamNutritionService $nutritionService;
    protected NutritionDataTransformationService $transformationService;
    
    public function __construct(
        EdamamNutritionService $nutritionService,
        NutritionDataTransformationService $transformationService
    ) {
        $this->nutritionService = $nutritionService;
        $this->transformationService = $transformationService;
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
            $productId = $data['product_id'] ?? null;
            
            // Map 'ingredients' to 'ingr' for service compatibility
            if (isset($data['ingredients'])) {
                $data['ingr'] = $data['ingredients'];
                unset($data['ingredients']);
            }
            
            // Create cache data excluding product_id
            $cacheData = array_diff_key($data, ['product_id' => '']);
            
            // Generate cache key for nutrition analysis
            $cacheKey = 'nutrition_analysis_' . md5(json_encode($cacheData));
            
            // Check cache first (cache for 1 hour)
            $result = Cache::remember($cacheKey, 3600, function () use ($cacheData) {
                return $this->nutritionService->analyzeNutrition($cacheData);
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
    
    /**
     * Check if nutrition data exists for a product and user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkNutritionData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id'
            ]);

            $nutritionData = NutritionalData::where('product_id', $validated['product_id'])
                ->where('user_id', auth()->id())
                ->first();

            return response()->json([
                'success' => true,
                'exists' => $nutritionData !== null,
                'data' => $nutritionData ? $nutritionData->toFrontendStructure() : null
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error checking nutrition data: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'error' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to check nutrition data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Load nutrition data for a product and user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function loadNutritionData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id'
            ]);

            $nutritionData = NutritionalData::where('product_id', $validated['product_id'])
                ->where('user_id', auth()->id())
                ->first();

            if (!$nutritionData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No nutrition data found for this product'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Nutrition data loaded successfully',
                'data' => $nutritionData->toFrontendStructure()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error loading nutrition data: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'error' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load nutrition data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save nutrition analysis data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function saveNutritionData(Request $request): JsonResponse
    {
        try {
            // Validate the frontend request structure
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'basic_nutrition' => 'required|array',
                'basic_nutrition.total_calories' => 'required|numeric',
                'basic_nutrition.servings' => 'required|numeric',
                'basic_nutrition.weight_per_serving' => 'required|numeric',
                'macronutrients' => 'required|array',
                'macronutrients.protein' => 'required|numeric',
                'macronutrients.carbohydrates' => 'required|numeric',
                'macronutrients.fat' => 'required|numeric',
                'macronutrients.fiber' => 'required|numeric',
                'micronutrients' => 'required|array',
                'daily_values' => 'required|array',
                'health_labels' => 'nullable|array',
                'diet_labels' => 'nullable|array',
                'allergens' => 'nullable|array',
                'warnings' => 'nullable|array',
                'high_nutrients' => 'nullable|array',
                'nutrition_summary' => 'nullable|array',
                'analysis_metadata' => 'required|array',
                'analysis_metadata.analyzed_at' => 'required|string',
                'analysis_metadata.ingredient_query' => 'required|string',
                'analysis_metadata.product_name' => 'nullable|string',
            ]);

            // Create nutrition data using the new model method
            $savedData = NutritionalData::createFromFrontendData($validated);

            return response()->json([
                'success' => true,
                'message' => 'Nutrition data saved successfully',
                'data' => $savedData->toFrontendStructure()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error saving nutrition data: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'error' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save nutrition data: ' . $e->getMessage()
            ], 500);
        }
    }

}