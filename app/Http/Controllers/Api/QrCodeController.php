<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Models\QrCodeAnalytics;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\Logo\LogoInterface;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * QR Code Controller
 * 
 * Handles QR code generation, management, and analytics for products.
 * Uses SimpleSoftwareIO QrCode package for QR code generation.
 * 
 * @see \SimpleSoftwareIO\QrCode\Facades\QrCode
 * @see \Illuminate\Support\Facades\Storage
 * @method static \Illuminate\Contracts\Filesystem\Filesystem disk(string $name = null)
 */
class QrCodeController extends Controller
{
    /**
     * Get the public storage disk with proper typing
     * 
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    private function getPublicDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk('public');
    }
    /**
     * Generate QR code for a product
     */
    public function generate(Request $request, $productId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'size' => 'integer|min:100|max:1000',
                'format' => 'string|in:png,svg,jpg',
                'error_correction' => 'string|in:L,M,Q,H',
                'margin' => 'integer|min:0|max:10',
                'color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
                'background_color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/'
            ]);

            if ($validator->fails()) {
                Log::error('QR Code validation failed', [
                    'errors' => $validator->errors(),
                    'request_data' => $request->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find the product
            $product = Product::where('id', $productId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found or access denied'
                ], 404);
            }

            // Check if user has permission to generate QR codes
            /** @var User $user */
            $user = Auth::user();
            $user->load('membershipPlan');
            if (!$user->canGenerateQrCodes()) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR code generation requires a premium membership',
                    'upgrade_required' => true
                ], 403);
            }

            // Check if this is a premium user for advanced features
            $isPremiumUser = $user->membershipPlan && $user->membershipPlan->name !== 'Basic';
            $isEnterpriseUser = $user->membershipPlan && $user->membershipPlan->name === 'Enterprise';

            // Generate public URL for the product
            $publicUrl = config('app.url') . '/public/product/' . $product->id;

            // Set QR code options with improved defaults for better corner rendering
            $size = $request->get('size', 300);
            $format = $request->get('format', 'png'); // Default to PNG since GD is available
            $errorCorrection = $request->get('error_correction', 'H'); // Default to High for better corner visibility
            $margin = $request->get('margin', 1); // Reduced margin for better corner visibility
            $color = $request->get('color', '#000000');
            $backgroundColor = $request->get('background_color', '#FFFFFF');

            // Map error correction levels
            $errorCorrectionMap = [
                'L' => ErrorCorrectionLevel::Low,
                'M' => ErrorCorrectionLevel::Medium,
                'Q' => ErrorCorrectionLevel::Quartile,
                'H' => ErrorCorrectionLevel::High,
            ];

            // Check if GD extension is available for PNG/JPG format
            // Note: Laravel's artisan serve may not load GD properly, so we'll try to use it anyway
            if (($format === 'png' || $format === 'jpg') && !extension_loaded('gd')) {
                // Try to check if GD functions exist as a secondary check
                if (!function_exists('imagecreate')) {
                    // Fallback to SVG if GD is truly not available
                    $originalFormat = $format;
                    $format = 'svg';
                    Log::warning('GD extension not available, falling back to SVG format', [
                        'requested_format' => $originalFormat,
                        'fallback_format' => 'svg',
                        'extension_loaded' => extension_loaded('gd'),
                        'imagecreate_exists' => function_exists('imagecreate')
                    ]);
                } else {
                    Log::info('GD extension reported as not loaded but GD functions are available, proceeding with PNG/JPG', [
                        'requested_format' => $format,
                        'extension_loaded' => extension_loaded('gd'),
                        'imagecreate_exists' => function_exists('imagecreate')
                    ]);
                }
            }

            // Generate QR code using Endroid package with proper configuration
            if ($format === 'svg') {
                $builder = new Builder(
                    writer: new SvgWriter(),
                    data: $publicUrl,
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: $errorCorrectionMap[$errorCorrection],
                    size: $size,
                    margin: $margin,
                    roundBlockSizeMode: RoundBlockSizeMode::Margin
                );
            } else {
                // Convert hex colors to RGB for PNG/JPG format
                $foregroundColor = [
                    'r' => hexdec(substr($color, 1, 2)),
                    'g' => hexdec(substr($color, 3, 2)),
                    'b' => hexdec(substr($color, 5, 2))
                ];
                $backgroundColorRgb = [
                    'r' => hexdec(substr($backgroundColor, 1, 2)),
                    'g' => hexdec(substr($backgroundColor, 3, 2)),
                    'b' => hexdec(substr($backgroundColor, 5, 2))
                ];
                
                // Use PngWriter for both PNG and JPG formats
                $writer = new PngWriter();
                
                $builder = new Builder(
                    writer: $writer,
                    data: $publicUrl,
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: $errorCorrectionMap[$errorCorrection],
                    size: $size,
                    margin: $margin,
                    foregroundColor: new Color($foregroundColor['r'], $foregroundColor['g'], $foregroundColor['b']),
                    backgroundColor: new Color($backgroundColorRgb['r'], $backgroundColorRgb['g'], $backgroundColorRgb['b']),
                    roundBlockSizeMode: RoundBlockSizeMode::Margin
                );
            }

            $result = $builder->build();
            $qrCode = $result->getString();

            // Convert PNG to JPG if needed
            if ($format === 'jpg') {
                // Create image from PNG string
                $image = imagecreatefromstring($qrCode);
                if ($image === false) {
                    throw new \Exception('Failed to create image from QR code data');
                }
                
                // Create a white background for JPG (since JPG doesn't support transparency)
                $width = imagesx($image);
                $height = imagesy($image);
                $jpgImage = imagecreatetruecolor($width, $height);
                
                // Set background color
                $bgColor = imagecolorallocate($jpgImage, $backgroundColorRgb['r'], $backgroundColorRgb['g'], $backgroundColorRgb['b']);
                imagefill($jpgImage, 0, 0, $bgColor);
                
                // Copy the QR code onto the background
                imagecopy($jpgImage, $image, 0, 0, 0, 0, $width, $height);
                
                // Convert to JPG string
                ob_start();
                imagejpeg($jpgImage, null, 90); // 90% quality
                $qrCode = ob_get_contents();
                ob_end_clean();
                
                // Clean up memory
                imagedestroy($image);
                imagedestroy($jpgImage);
            }

            // Generate unique filename
            $filename = 'qr-codes/' . $product->id . '_' . Str::random(10) . '.' . $format;
            
            // Store QR code image
            $this->getPublicDisk()->put($filename, $qrCode);

            // Check if QR code already exists for this product
            $existingQrCode = QrCode::where('product_id', $product->id)->first();

            if ($existingQrCode) {
                // Delete old QR code image if it exists
                if ($existingQrCode->image_path && $this->getPublicDisk()->exists($existingQrCode->image_path)) {
                     $this->getPublicDisk()->delete($existingQrCode->image_path);
                }
                
                // Update existing QR code with premium features
                $updateData = [
                    'url_slug' => $publicUrl,
                    'image_path' => $filename,
                    'is_premium' => $isPremiumUser,
                ];
                
                // Generate unique code for premium users
                if ($isPremiumUser && !$existingQrCode->unique_code) {
                    $updateData['unique_code'] = 'QR-' . strtoupper(uniqid()) . '-' . $product->id;
                }
                
                $existingQrCode->update($updateData);
                $qrCodeRecord = $existingQrCode;
            } else {
                // Create new QR code record with premium features
                $createData = [
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'url_slug' => $publicUrl,
                    'image_path' => $filename,
                    'scan_count' => 0,
                    'is_premium' => $isPremiumUser,
                ];
                
                // Generate unique code for premium users
                if ($isPremiumUser) {
                    $createData['unique_code'] = 'QR-' . strtoupper(uniqid()) . '-' . $product->id;
                }
                
                $qrCodeRecord = QrCode::create($createData);
                
                // Track QR code creation analytics
                QrCodeAnalytics::trackCreation(
                    $user->id,
                    $qrCodeRecord->id,
                    QrCodeAnalytics::TYPE_PRODUCT,
                    [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'format' => $format,
                        'size' => $size
                    ]
                );
            }

            // Track usage
            $user->incrementUsage('qr_codes');

            // Ensure the QR code record has all necessary fields
            $qrCodeData = [
                'id' => $qrCodeRecord->id,
                'product_id' => $qrCodeRecord->product_id,
                'url_slug' => $qrCodeRecord->url_slug,
                'image_path' => $qrCodeRecord->image_path,
                'scan_count' => $qrCodeRecord->scan_count,
                'last_scanned_at' => $qrCodeRecord->last_scanned_at,
                'created_at' => $qrCodeRecord->created_at,
                'updated_at' => $qrCodeRecord->updated_at,
            ];

            Log::info('QR Code generation successful', [
                'qr_code_data' => $qrCodeData,
                'qr_code_id' => $qrCodeRecord->id
            ]);

            // Prepare response data with premium features
            $qrCodeData = [
                'id' => $qrCodeRecord->id,
                'product_id' => $qrCodeRecord->product_id,
                'url_slug' => $qrCodeRecord->url_slug,
                'scan_count' => $qrCodeRecord->scan_count,
                'last_scanned_at' => $qrCodeRecord->last_scanned_at,
                'is_premium' => $qrCodeRecord->is_premium,
                'created_at' => $qrCodeRecord->created_at,
                'updated_at' => $qrCodeRecord->updated_at,
            ];
            
            // Add premium features to QR code data
            if ($isPremiumUser && $qrCodeRecord->unique_code) {
                $qrCodeData['unique_code'] = $qrCodeRecord->unique_code;
            }
            
            return response()->json([
                'success' => true,
                'message' => 'QR code generated successfully',
                'qr_code' => $qrCodeData,
                'image_url' => Storage::url($filename),
                'public_url' => $publicUrl,
                'download_url' => route('api.qr-codes.download', $qrCodeRecord->id),
                'user_plan' => [
                    'is_premium' => $isPremiumUser,
                    'is_enterprise' => $isEnterpriseUser,
                    'plan_name' => $user->membershipPlan ? $user->membershipPlan->name : 'Basic'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('QR Code generation failed', [
                'product_id' => $productId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR code. Please try again.'
            ], 500);
        }
    }

    /**
     * Generate QR code from URL content directly
     */
    public function generateFromUrl(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:2048',
                'type' => 'sometimes|string|in:url,custom',
                'size' => 'sometimes|integer|min:100|max:1000',
                'format' => 'sometimes|string|in:png,svg,jpg',
                'error_correction' => 'sometimes|string|in:L,M,Q,H',
                'margin' => 'sometimes|integer|min:0|max:10',
                'color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'background_color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            /** @var User $user */
            $user = Auth::user();
            $user->load('membershipPlan');
            if (!$user->canGenerateQrCodes()) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR code generation requires a premium membership',
                    'upgrade_required' => true
                ], 403);
            }

            // Check if this is a premium user for advanced features
            $isPremiumUser = $user->membershipPlan && $user->membershipPlan->name !== 'Basic';
            $isEnterpriseUser = $user->membershipPlan && $user->membershipPlan->name === 'Enterprise';

            // Get content from request
            $content = $request->get('content');
            $type = $request->get('type', 'url');

            // Set QR code options with improved defaults
            $size = $request->get('size', 300);
            $format = $request->get('format', 'png');
            $errorCorrection = $request->get('error_correction', 'H');
            $margin = $request->get('margin', 1);
            $color = $request->get('color', '#000000');
            $backgroundColor = $request->get('background_color', '#FFFFFF');

            // Map error correction levels
            $errorCorrectionMap = [
                'L' => ErrorCorrectionLevel::Low,
                'M' => ErrorCorrectionLevel::Medium,
                'Q' => ErrorCorrectionLevel::Quartile,
                'H' => ErrorCorrectionLevel::High,
            ];

            // Check if GD extension is available for PNG/JPG format
            if (($format === 'png' || $format === 'jpg') && !extension_loaded('gd')) {
                $originalFormat = $format;
                $format = 'svg';
                Log::warning('GD extension not available, falling back to SVG format', [
                    'requested_format' => $originalFormat,
                    'fallback_format' => 'svg'
                ]);
            }

            // Generate QR code using Endroid package
            if ($format === 'svg') {
                $builder = new Builder(
                    writer: new SvgWriter(),
                    data: $content,
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: $errorCorrectionMap[$errorCorrection],
                    size: $size,
                    margin: $margin,
                    roundBlockSizeMode: RoundBlockSizeMode::Margin
                );
            } else {
                // Convert hex colors to RGB for PNG/JPG format
                $foregroundColor = [
                    'r' => hexdec(substr($color, 1, 2)),
                    'g' => hexdec(substr($color, 3, 2)),
                    'b' => hexdec(substr($color, 5, 2))
                ];
                $backgroundColorRgb = [
                    'r' => hexdec(substr($backgroundColor, 1, 2)),
                    'g' => hexdec(substr($backgroundColor, 3, 2)),
                    'b' => hexdec(substr($backgroundColor, 5, 2))
                ];

                $builder = new Builder(
                    writer: new PngWriter(),
                    data: $content,
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: $errorCorrectionMap[$errorCorrection],
                    size: $size,
                    margin: $margin,
                    roundBlockSizeMode: RoundBlockSizeMode::Margin
                );
            }

            $result = $builder->build();
            $qrCode = $result->getString();

            // Handle JPG format conversion if needed
            if ($format === 'jpg') {
                $image = imagecreatefromstring($qrCode);
                $width = imagesx($image);
                $height = imagesy($image);
                
                $jpgImage = imagecreatetruecolor($width, $height);
                $bgColor = imagecolorallocate($jpgImage, $backgroundColorRgb['r'], $backgroundColorRgb['g'], $backgroundColorRgb['b']);
                imagefill($jpgImage, 0, 0, $bgColor);
                imagecopy($jpgImage, $image, 0, 0, 0, 0, $width, $height);
                
                ob_start();
                imagejpeg($jpgImage, null, 90);
                $qrCode = ob_get_contents();
                ob_end_clean();
                
                imagedestroy($image);
                imagedestroy($jpgImage);
            }

            // Generate unique filename
            $filename = 'qr-codes/url_' . Str::random(15) . '.' . $format;
            
            // Store QR code image
            $this->getPublicDisk()->put($filename, $qrCode);

            // Create QR code record (without product association)
            $createData = [
                'user_id' => $user->id, // Associate with the authenticated user
                'product_id' => null, // No product association for URL-based QR codes
                'url_slug' => $content,
                'image_path' => $filename,
                'scan_count' => 0,
                'is_premium' => $isPremiumUser,
            ];
            
            // Generate unique code for premium users
            if ($isPremiumUser) {
                $createData['unique_code'] = 'QR-URL-' . strtoupper(uniqid()) . '-' . $user->id;
            }
            
            $qrCodeRecord = QrCode::create($createData);
            
            // Track QR code creation analytics
            QrCodeAnalytics::trackCreation(
                $user->id,
                $qrCodeRecord->id,
                QrCodeAnalytics::TYPE_URL,
                [
                    'content' => $content,
                    'type' => $type,
                    'format' => $format,
                    'size' => $size
                ]
            );

            // Track usage
            $user->incrementUsage('qr_codes');

            // Prepare response data
            $qrCodeData = [
                'id' => $qrCodeRecord->id,
                'product_id' => null,
                'url_slug' => $qrCodeRecord->url_slug,
                'scan_count' => $qrCodeRecord->scan_count,
                'last_scanned_at' => $qrCodeRecord->last_scanned_at,
                'is_premium' => $qrCodeRecord->is_premium,
                'created_at' => $qrCodeRecord->created_at,
                'updated_at' => $qrCodeRecord->updated_at,
            ];
            
            // Add premium features to QR code data
            if ($isPremiumUser && $qrCodeRecord->unique_code) {
                $qrCodeData['unique_code'] = $qrCodeRecord->unique_code;
            }
            
            return response()->json([
                'success' => true,
                'message' => 'QR code generated successfully from URL',
                'qr_code' => $qrCodeData,
                'image_url' => Storage::url($filename),
                'public_url' => $content, // The content itself is the public URL
                'download_url' => route('api.qr-codes.download', $qrCodeRecord->id),
                'user_plan' => [
                    'is_premium' => $isPremiumUser,
                    'is_enterprise' => $isEnterpriseUser,
                    'plan_name' => $user->membershipPlan ? $user->membershipPlan->name : 'Basic'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('QR Code generation from URL failed', [
                'content' => $request->get('content'),
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR code from URL. Please try again.'
            ], 500);
        }
    }

    /**
     * Get QR codes for a product
     */
    public function show($productId): JsonResponse
    {
        try {
            $product = Product::where('id', $productId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found or access denied'
                ], 404);
            }

            /** @var User $user */
            $user = Auth::user();
            $user->load('membershipPlan');
            $isPremiumUser = $user->membershipPlan && $user->membershipPlan->name !== 'Basic';

            $qrCodes = QrCode::where('product_id', $product->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($qrCode) use ($isPremiumUser) {
                    $qrCodeData = [
                        'id' => $qrCode->id,
                        'url_slug' => $qrCode->url_slug,
                        'image_url' => $qrCode->image_path ? Storage::url($qrCode->image_path) : null,
                        'scan_count' => $qrCode->scan_count,
                        'last_scanned_at' => $qrCode->last_scanned_at,
                        'is_premium' => $qrCode->is_premium,
                        'created_at' => $qrCode->created_at,
                        'download_url' => route('api.qr-codes.download', $qrCode->id)
                    ];
                    
                    // Add premium features if user is premium
                    if ($isPremiumUser && $qrCode->is_premium && $qrCode->unique_code) {
                        $qrCodeData['unique_code'] = $qrCode->unique_code;
                    }
                    
                    return $qrCodeData;
                });

            return response()->json([
                'success' => true,
                'data' => $qrCodes
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch QR codes', [
                'product_id' => $productId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch QR codes'
            ], 500);
        }
    }

    /**
     * Download QR code image
     */
    public function download($qrCodeId)
    {
        try {
            // Find QR code by user_id (handles both product-based and URL-generated QR codes)
            $qrCode = QrCode::where('user_id', Auth::id())->findOrFail($qrCodeId);

            if (!$qrCode->image_path || !$this->getPublicDisk()->exists($qrCode->image_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR code image not found'
                ], 404);
            }

            // Generate appropriate filename based on QR code type
            if ($qrCode->product_id) {
                $filename = 'qr-code-product-' . $qrCode->product_id . '.' . pathinfo($qrCode->image_path, PATHINFO_EXTENSION);
            } else {
                $filename = 'qr-code-custom-' . $qrCode->id . '.' . pathinfo($qrCode->image_path, PATHINFO_EXTENSION);
            }
            
            $filePath = Storage::disk('public')->path($qrCode->image_path);
            return response()->download($filePath, $filename);

        } catch (\Exception $e) {
            Log::error('QR Code download failed', [
                'qr_code_id' => $qrCodeId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download QR code'
            ], 500);
        }
    }

    /**
     * Delete QR code
     */
    public function destroy($qrCodeId): JsonResponse
    {
        try {
            // Find QR code by user_id (handles both product-based and URL-generated QR codes)
            $qrCode = QrCode::where('user_id', Auth::id())->findOrFail($qrCodeId);
            
            // Determine QR code type for analytics
            $qrCodeType = $qrCode->product_id ? QrCodeAnalytics::TYPE_PRODUCT : QrCodeAnalytics::TYPE_URL;
            
            // Prepare metadata for analytics
            $metadata = [
                'scan_count' => $qrCode->scan_count,
                'last_scanned_at' => $qrCode->last_scanned_at,
            ];
            
            if ($qrCode->product_id) {
                $metadata['product_id'] = $qrCode->product_id;
                if ($qrCode->product) {
                    $metadata['product_name'] = $qrCode->product->name;
                }
            } else {
                $metadata['url_content'] = $qrCode->url_slug;
            }
            
            // Track QR code deletion analytics before deleting
            QrCodeAnalytics::trackDeletion(
                Auth::id(),
                $qrCode->id,
                $qrCodeType,
                $metadata
            );

            // Delete image file if it exists
            if ($qrCode->image_path && $this->getPublicDisk()->exists($qrCode->image_path)) {
                 $this->getPublicDisk()->delete($qrCode->image_path);
            }

            // Delete QR code record
            $qrCode->delete();

            return response()->json([
                'success' => true,
                'message' => 'QR code deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('QR Code deletion failed', [
                'qr_code_id' => $qrCodeId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete QR code'
            ], 500);
        }
    }

    /**
     * Get QR code creation and deletion analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $days = $request->get('days', 30);
            
            // Get creation and deletion analytics
            $creationAnalytics = QrCodeAnalytics::getCreationAnalytics($user->id, $days);
            $deletionAnalytics = QrCodeAnalytics::getDeletionAnalytics($user->id, $days);
            $trends = QrCodeAnalytics::getTrends($user->id, $days);
            
            // Get totals
            $totalCreated = QrCodeAnalytics::getTotalCreated($user->id);
            $totalDeleted = QrCodeAnalytics::getTotalDeleted($user->id);
            $netQrCodes = $totalCreated - $totalDeleted;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'creation_analytics' => $creationAnalytics,
                    'deletion_analytics' => $deletionAnalytics,
                    'trends' => $trends,
                    'totals' => [
                        'created' => $totalCreated,
                        'deleted' => $totalDeleted,
                        'net_qr_codes' => $netQrCodes
                    ],
                    'period_days' => $days
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('QR Code analytics retrieval failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve QR code analytics'
            ], 500);
        }
    }

    /**
     * Get all QR codes for the authenticated user
     */
    public function index(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            
            Log::info('🔄 [QR_CONTROLLER] Starting QR codes index request', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'timestamp' => now()->toISOString()
            ]);
            
            // Get all QR codes for the user - both with and without products
            Log::info('🔍 [QR_CONTROLLER] Building QR codes query for user', [
                'user_id' => $user->id
            ]);
            
            $qrCodes = QrCode::where(function ($query) use ($user) {
                // QR codes with products
                $query->whereHas('product', function ($productQuery) use ($user) {
                    $productQuery->where('user_id', $user->id);
                })
                // OR QR codes without products but created by this user
                ->orWhere(function ($urlQuery) use ($user) {
                    $urlQuery->whereNull('product_id')
                             ->where('user_id', $user->id);
                });
            })
            ->with('product:id,name')
            ->orderBy('created_at', 'desc')
            ->get();
            
            Log::info('📊 [QR_CONTROLLER] Raw QR codes query result', [
                'user_id' => $user->id,
                'qr_codes_count' => $qrCodes->count(),
                'qr_codes_raw' => $qrCodes->map(function ($qr) {
                    return [
                        'id' => $qr->id,
                        'user_id' => $qr->user_id,
                        'product_id' => $qr->product_id,
                        'url_slug' => $qr->url_slug,
                        'image_path' => $qr->image_path,
                        'scan_count' => $qr->scan_count,
                        'created_at' => $qr->created_at,
                        'product_name' => $qr->product ? $qr->product->name : null
                    ];
                })->toArray()
            ]);
            
            $mappedQrCodes = $qrCodes->map(function ($qrCode) {
                return [
                    'id' => $qrCode->id,
                    'url_slug' => $qrCode->url_slug,
                    'image_url' => $qrCode->image_path ? Storage::url($qrCode->image_path) : null,
                    'scan_count' => $qrCode->scan_count,
                    'last_scanned_at' => $qrCode->last_scanned_at,
                    'created_at' => $qrCode->created_at,
                    'download_url' => route('api.qr-codes.download', $qrCode->id),
                    'product_name' => $qrCode->product ? $qrCode->product->name : 'URL QR Code',
                    'product_id' => $qrCode->product ? $qrCode->product->id : null
                ];
            });
            
            Log::info('📋 [QR_CONTROLLER] Mapped QR codes for response', [
                'user_id' => $user->id,
                'mapped_count' => $mappedQrCodes->count(),
                'mapped_qr_codes' => $mappedQrCodes->toArray()
            ]);

            $response = [
                'success' => true,
                'data' => $mappedQrCodes
            ];
            
            Log::info('✅ [QR_CONTROLLER] QR codes index request completed successfully', [
                'user_id' => $user->id,
                'response_data_count' => count($response['data']),
                'response_success' => $response['success']
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('❌ [QR_CONTROLLER] Failed to fetch user QR codes', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch QR codes'
            ], 500);
        }
    }

    /**
     * Get QR code analytics
     */
    public function analytics(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $user->load('membershipPlan');
            
            // Check if user has premium access for analytics
            $isPremiumUser = $user->membershipPlan && $user->membershipPlan->name !== 'Basic';
            
            if (!$isPremiumUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR code analytics requires a premium membership',
                    'upgrade_required' => true
                ], 403);
            }
            
            $analytics = QrCode::whereHas('product', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->selectRaw('COUNT(*) as total_qr_codes')
            ->selectRaw('SUM(scan_count) as total_scans')
            ->selectRaw('AVG(scan_count) as avg_scans_per_qr')
            ->selectRaw('MAX(scan_count) as max_scans')
            ->first();

            $topPerformingQrCodes = QrCode::whereHas('product', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with('product:id,name')
            ->orderBy('scan_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($qrCode) {
                return [
                    'id' => $qrCode->id,
                    'product_name' => $qrCode->product->name,
                    'scan_count' => $qrCode->scan_count,
                    'last_scanned_at' => $qrCode->last_scanned_at,
                    'unique_code' => $qrCode->unique_code
                ];
            });

            // Get scan trends for the last 30 days (premium feature)
            $scanTrends = QrCode::whereHas('product', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('last_scanned_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(last_scanned_at) as scan_date, COUNT(*) as daily_scans')
            ->groupBy('scan_date')
            ->orderBy('scan_date')
            ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $analytics,
                    'top_performing' => $topPerformingQrCodes,
                    'scan_trends' => $scanTrends
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('QR Code analytics failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics'
            ], 500);
        }
    }

    /**
      * Get detailed analytics for a specific QR code (premium feature)
      */
     public function qrCodeAnalytics($qrCodeId): JsonResponse
     {
         try {
             /** @var User $user */
             $user = Auth::user();
             $user->load('membershipPlan');
             
             // Check if user has premium access
             $isPremiumUser = $user->membershipPlan && $user->membershipPlan->name !== 'Basic';
             
             if (!$isPremiumUser) {
                 return response()->json([
                     'success' => false,
                     'message' => 'Detailed QR code analytics requires a premium membership',
                     'upgrade_required' => true
                 ], 403);
             }
 
             $qrCode = QrCode::whereHas('product', function ($query) use ($user) {
                 $query->where('user_id', $user->id);
             })->findOrFail($qrCodeId);
 
             // Get detailed analytics using the model method
             $analytics = $qrCode->getScanAnalytics();
             $analytics['qr_code_id'] = $qrCode->id;
             $analytics['days_active'] = $qrCode->created_at->diffInDays(now());
             $analytics['avg_scans_per_day'] = $qrCode->scan_count > 0 && $qrCode->created_at->diffInDays(now()) > 0 
                 ? round($qrCode->scan_count / max(1, $qrCode->created_at->diffInDays(now())), 2) 
                 : 0;
 
             return response()->json([
                 'success' => true,
                 'data' => $analytics
             ]);
 
         } catch (\Exception $e) {
             Log::error('QR Code detailed analytics failed', [
                 'qr_code_id' => $qrCodeId,
                 'user_id' => Auth::id(),
                 'error' => $e->getMessage()
             ]);
 
             return response()->json([
                 'success' => false,
                 'message' => 'Failed to fetch QR code analytics'
             ], 500);
         }
     }

     /**
      * Track QR code scan with enhanced analytics
      */
     public function trackScan(Request $request, $qrCodeId): JsonResponse
     {
         try {
             $qrCode = QrCode::findOrFail($qrCodeId);
             
             // Collect analytics data
             $analyticsData = [
                 'user_agent' => $request->userAgent(),
                 'ip_address' => $request->ip(),
                 'referrer' => $request->header('referer'),
                 'device_type' => $this->detectDeviceType($request->userAgent()),
                 'location' => $request->header('cf-ipcountry') ?? 'Unknown'
             ];
             
             // Track the scan using the model method
             $qrCode->trackScan($analyticsData);
             
             return response()->json([
                 'success' => true,
                 'message' => 'Scan tracked successfully',
                 'redirect_url' => $qrCode->url_slug
             ]);
             
         } catch (\Exception $e) {
             Log::error('QR Code scan tracking failed', [
                 'qr_code_id' => $qrCodeId,
                 'error' => $e->getMessage()
             ]);
             
             return response()->json([
                 'success' => false,
                 'message' => 'Failed to track scan'
             ], 500);
         }
     }
     
     /**
      * Detect device type from user agent
      */
     private function detectDeviceType(string $userAgent): string
     {
         if (preg_match('/mobile|android|iphone|ipad|phone/i', $userAgent)) {
             return 'mobile';
         } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
             return 'tablet';
         } else {
             return 'desktop';
         }
     }
}