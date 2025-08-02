<?php

namespace App\Services;

use App\Models\User;
use App\Models\Product;
use App\Models\QrCodeAnalytics;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UsageTrackingService
{
    /**
     * Get current usage statistics for a user
     */
    public function getCurrentUsage(User $user): array
    {
        try {
            Log::info('Calculating current usage for user', [
                'user_id' => $user->id,
                'plan_name' => $user->membershipPlan?->name
            ]);
            
            $currentMonth = Carbon::now()->startOfMonth();
            
            // Count products created this month
            $productsThisMonth = Product::where('user_id', $user->id)
                ->where('created_at', '>=', $currentMonth)
                ->count();
            
            // Count total products (for overall tracking)
            $totalProducts = Product::where('user_id', $user->id)->count();
            
            // For labels, we'll count based on products for now
            // In the future, this could be a separate labels table
            $labelsThisMonth = $productsThisMonth; // Assuming 1 label per product
            $totalLabels = $totalProducts;
            
            // Get QR code analytics using the QrCodeAnalytics model
            $totalQrCodesCreated = QrCodeAnalytics::getTotalCreated($user->id);
            $totalQrCodesDeleted = QrCodeAnalytics::getTotalDeleted($user->id);
            $currentQrCodes = $totalQrCodesCreated - $totalQrCodesDeleted;
            
            // Get QR codes created this month
            $qrCodesThisMonth = QrCodeAnalytics::where('user_id', $user->id)
                ->where('event_type', 'created')
                ->where('event_date', '>=', $currentMonth)
                ->count();
            
            $usage = [
                'products' => [
                    'current_month' => $productsThisMonth,
                    'total' => $totalProducts,
                    'limit' => $user->membershipPlan?->product_limit ?? 0,
                    'unlimited' => $user->membershipPlan?->hasUnlimitedProducts() ?? false
                ],
                'labels' => [
                    'current_month' => $labelsThisMonth,
                    'total' => $totalLabels,
                    'limit' => $user->membershipPlan?->label_limit ?? 0,
                    'unlimited' => $user->membershipPlan?->hasUnlimitedLabels() ?? false
                ],
                'qr_codes' => [
                    'current_month' => $qrCodesThisMonth,
                    'total' => $currentQrCodes,
                    'limit' => $user->membershipPlan?->qr_code_limit ?? 0,
                    'unlimited' => $user->membershipPlan?->hasUnlimitedQrCodes() ?? false
                ],
                'period' => [
                    'start' => $currentMonth->toDateString(),
                    'end' => $currentMonth->copy()->endOfMonth()->toDateString()
                ]
            ];
            
            Log::info('Successfully calculated user usage', [
                'user_id' => $user->id,
                'products_used' => $productsThisMonth,
                'product_limit' => $user->membershipPlan?->product_limit,
                'labels_used' => $labelsThisMonth,
                'label_limit' => $user->membershipPlan?->label_limit,
                'qr_codes_used' => $qrCodesThisMonth,
                'qr_codes_total' => $currentQrCodes,
                'qr_code_limit' => $user->membershipPlan?->qr_code_limit
            ]);
            
            return $usage;
        } catch (\Exception $e) {
            Log::error('Failed to calculate user usage', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return default usage on error
            return [
                'products' => [
                    'current_month' => 0,
                    'total' => 0,
                    'limit' => 0,
                    'unlimited' => false
                ],
                'labels' => [
                    'current_month' => 0,
                    'total' => 0,
                    'limit' => 0,
                    'unlimited' => false
                ],
                'qr_codes' => [
                    'current_month' => 0,
                    'total' => 0,
                    'limit' => 0,
                    'unlimited' => false
                ],
                'period' => [
                    'start' => Carbon::now()->startOfMonth()->toDateString(),
                    'end' => Carbon::now()->endOfMonth()->toDateString()
                ]
            ];
        }
    }
    
    /**
     * Check if user can create a new product
     */
    public function canCreateProduct(User $user): bool
    {
        try {
            $usage = $this->getCurrentUsage($user);
            
            // If unlimited, always allow
            if ($usage['products']['unlimited']) {
                return true;
            }
            
            // Check if under limit
            $canCreate = $usage['products']['current_month'] < $usage['products']['limit'];
            
            Log::info('Product creation permission check', [
                'user_id' => $user->id,
                'can_create' => $canCreate,
                'current_usage' => $usage['products']['current_month'],
                'limit' => $usage['products']['limit']
            ]);
            
            return $canCreate;
        } catch (\Exception $e) {
            Log::error('Failed to check product creation permission', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Check if user can create a new label
     */
    public function canCreateLabel(User $user): bool
    {
        try {
            $usage = $this->getCurrentUsage($user);
            
            // If unlimited, always allow
            if ($usage['labels']['unlimited']) {
                return true;
            }
            
            // Check if under limit
            $canCreate = $usage['labels']['current_month'] < $usage['labels']['limit'];
            
            Log::info('Label creation permission check', [
                'user_id' => $user->id,
                'can_create' => $canCreate,
                'current_usage' => $usage['labels']['current_month'],
                'limit' => $usage['labels']['limit']
            ]);
            
            return $canCreate;
        } catch (\Exception $e) {
            Log::error('Failed to check label creation permission', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get usage percentage for progress bars
     */
    public function getUsagePercentages(User $user): array
    {
        try {
            $usage = $this->getCurrentUsage($user);
            
            $productPercentage = 0;
            $labelPercentage = 0;
            
            if (!$usage['products']['unlimited'] && $usage['products']['limit'] > 0) {
                $productPercentage = min(100, ($usage['products']['current_month'] / $usage['products']['limit']) * 100);
            }
            
            if (!$usage['labels']['unlimited'] && $usage['labels']['limit'] > 0) {
                $labelPercentage = min(100, ($usage['labels']['current_month'] / $usage['labels']['limit']) * 100);
            }
            
            return [
                'products' => round($productPercentage, 1),
                'labels' => round($labelPercentage, 1)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to calculate usage percentages', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'products' => 0,
                'labels' => 0
            ];
        }
    }
}