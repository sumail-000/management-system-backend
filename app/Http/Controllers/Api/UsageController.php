<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UsageTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UsageController extends Controller
{
    protected $usageService;
    
    public function __construct(UsageTrackingService $usageService)
    {
        $this->usageService = $usageService;
    }
    
    /**
     * Get current user's usage statistics
     */
    public function getCurrentUsage(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            Log::info('Fetching user usage statistics', [
                'user_id' => $user->id,
                'plan_name' => $user->membershipPlan?->name
            ]);
            
            $usage = $this->usageService->getCurrentUsage($user);
            $percentages = $this->usageService->getUsagePercentages($user);
            
            $response = [
                'usage' => $usage,
                'percentages' => $percentages,
                'permissions' => [
                    'can_create_product' => $this->usageService->canCreateProduct($user),
                    'can_create_label' => $this->usageService->canCreateLabel($user)
                ]
            ];
            
            Log::info('Successfully retrieved user usage statistics', [
                'user_id' => $user->id,
                'products_used' => $usage['products']['current_month'],
                'labels_used' => $usage['labels']['current_month']
            ]);
            
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user usage statistics', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch usage statistics'
            ], 500);
        }
    }
    
    /**
     * Check if user can perform a specific action
     */
    public function checkPermission(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'action' => 'required|in:create_product,create_label'
            ]);
            
            $user = Auth::user();
            $action = $request->action;
            
            Log::info('Checking user permission', [
                'user_id' => $user->id,
                'action' => $action,
                'plan_name' => $user->membershipPlan?->name
            ]);
            
            $canPerform = false;
            
            switch ($action) {
                case 'create_product':
                    $canPerform = $this->usageService->canCreateProduct($user);
                    break;
                case 'create_label':
                    $canPerform = $this->usageService->canCreateLabel($user);
                    break;
            }
            
            Log::info('Permission check result', [
                'user_id' => $user->id,
                'action' => $action,
                'can_perform' => $canPerform
            ]);
            
            return response()->json([
                'action' => $action,
                'can_perform' => $canPerform,
                'usage' => $this->usageService->getCurrentUsage($user)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check user permission', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'action' => $request->action ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check permission'
            ], 500);
        }
    }
}