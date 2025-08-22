<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Label;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LabelController extends Controller
{
    /**
     * Log a label generation event for a user's product.
     * This creates a row in the labels table so analytics and usage tracking can rely on real data.
     */
    public function logGeneration(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id'   => 'required|integer|exists:products,id',
                'name'         => 'nullable|string|max:255',
                // Frontend uses "FDA Vertical", "FDA Tabular", "FDA Linear" labels. We normalize to enum below.
                'format'       => 'nullable|string|in:vertical,horizontal,tabular,linear',
                'language'     => 'nullable|string|in:english,arabic,bilingual',
                'unit_system'  => 'nullable|string|in:metric,imperial',
                'qr_code_id'   => 'nullable|integer|exists:qr_codes,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $userId = Auth::id();
            $product = Product::where('id', $request->product_id)
                ->where('user_id', $userId)
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found or does not belong to the authenticated user',
                ], 404);
            }

            // Normalize format if frontend sends human-readable form
            $normalizedFormat = $request->format;
            if (!$normalizedFormat && $request->has('format')) {
                $formatRaw = strtolower((string) $request->format);
                if (str_contains($formatRaw, 'vertical')) $normalizedFormat = 'vertical';
                elseif (str_contains($formatRaw, 'horizontal')) $normalizedFormat = 'horizontal';
                elseif (str_contains($formatRaw, 'tabular')) $normalizedFormat = 'tabular';
                elseif (str_contains($formatRaw, 'linear')) $normalizedFormat = 'linear';
            }

            // Basic idempotency: If a label was created for this product in the last minute with same key attrs, return it
            $recentExisting = Label::where('product_id', $product->id)
                ->orderByDesc('created_at')
                ->first();

            if ($recentExisting) {
                $createdWithinOneMinute = $recentExisting->created_at && $recentExisting->created_at->gt(now()->subMinute());
                $sameAttrs = (
                    ($normalizedFormat ? $recentExisting->format === $normalizedFormat : true) &&
                    ($request->language ? $recentExisting->language === $request->language : true) &&
                    ($request->unit_system ? $recentExisting->unit_system === $request->unit_system : true)
                );
                if ($createdWithinOneMinute && $sameAttrs) {
                    Log::info('Returning recent existing label for idempotency', [
                        'product_id' => $product->id,
                        'label_id'   => $recentExisting->id,
                    ]);
                    return response()->json([
                        'success' => true,
                        'message' => 'Label generation already recorded recently',
                        'already_exists' => true,
                        'data' => $recentExisting,
                    ]);
                }
            }

            $label = Label::create([
                'product_id'  => $product->id,
                'name'        => $request->get('name') ?: ($product->name ? ($product->name . ' Label') : 'Nutrition Label'),
                'format'      => $normalizedFormat ?: 'vertical',
                'language'    => $request->get('language', 'bilingual'),
                'unit_system' => $request->get('unit_system', 'metric'),
                'qr_code_id'  => $request->get('qr_code_id'),
                'logo_path'   => null,
            ]);

            Log::info('Label generation logged', [
                'user_id'    => $userId,
                'product_id' => $product->id,
                'label_id'   => $label->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Label generation logged successfully',
                'data'    => $label,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to log label generation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to log label generation',
            ], 500);
        }
    }
}
