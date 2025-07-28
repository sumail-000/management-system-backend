<?php

namespace App\Http\Controllers;

use App\Models\NutritionAutoTag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NutritionAutoTagController extends Controller
{
    /**
     * Save nutrition auto tags data
     */
    public function saveAutoTags(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:products,id',
                'auto_tags' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Start database transaction
            DB::beginTransaction();

            try {
                // Create the nutrition auto tag record
                $nutritionAutoTag = NutritionAutoTag::create([
                    'product_id' => $request->product_id,
                    'auto_tags' => $request->auto_tags,
                    'analyzed_at' => now()
                ]);

                // Commit the transaction
                DB::commit();

                Log::info('Nutrition auto tags saved successfully', [
                    'id' => $nutritionAutoTag->id,
                    'product_id' => $request->product_id,
                    'auto_tags_count' => count($request->auto_tags)
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Auto tags data saved successfully',
                    'data' => [
                        'id' => $nutritionAutoTag->id,
                        'product_id' => $nutritionAutoTag->product_id,
                        'analyzed_at' => $nutritionAutoTag->analyzed_at
                    ]
                ], 201);

            } catch (\Exception $e) {
                // Rollback the transaction
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to save nutrition auto tags', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save auto tags data. Please try again.'
            ], 500);
        }
    }
}