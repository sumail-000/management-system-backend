<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MembershipPlanController extends Controller
{
    /**
     * Get all membership plans
     */
    public function index(): JsonResponse
    {
        try {
            Log::info('Fetching all membership plans', [
                'user_id' => Auth::id(),
                'timestamp' => now()
            ]);
            
            $plans = MembershipPlan::orderBy('price')->get();
            

            
            return response()->json($plans);
        } catch (\Exception $e) {
            Log::error('Failed to fetch membership plans', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch membership plans'
            ], 500);
        }
    }
    
    /**
     * Get a specific membership plan
     */
    public function show($id): JsonResponse
    {
        try {
            Log::info('Fetching specific membership plan', [
                'plan_id' => $id,
                'user_id' => Auth::id()
            ]);
            
            $plan = MembershipPlan::findOrFail($id);
            
            Log::info('Successfully retrieved membership plan', [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'user_id' => Auth::id()
            ]);
            
            return response()->json($plan);
        } catch (\Exception $e) {
            Log::error('Failed to fetch membership plan', [
                'error' => $e->getMessage(),
                'plan_id' => $id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Membership plan not found'
            ], 404);
        }
    }
    
    /**
     * Get current user's membership plan
     */
    public function getCurrentPlan(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            
            Log::info('Fetching current user membership plan', [
                'user_id' => $user->id,
                'current_plan_id' => $user->membership_plan_id
            ]);
            
            if (!$user->membershipPlan) {
                Log::warning('User has no membership plan assigned', [
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'No membership plan assigned'
                ], 404);
            }
            
            Log::info('Successfully retrieved user membership plan', [
                'user_id' => $user->id,
                'plan_name' => $user->membershipPlan->name,
                'plan_id' => $user->membershipPlan->id
            ]);
            
            return response()->json($user->membershipPlan);
        } catch (\Exception $e) {
            Log::error('Failed to fetch current membership plan', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch current membership plan'
            ], 500);
        }
    }
    
    /**
     * Get plan recommendations for the current user
     */
    public function getRecommendations(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $currentPlan = $user->membershipPlan;
            
            Log::info('Fetching plan recommendations', [
                'user_id' => $user->id,
                'current_plan_id' => $currentPlan?->id,
                'current_plan_name' => $currentPlan?->name
            ]);
            
            if (!$currentPlan) {
                Log::warning('User has no membership plan for recommendations', [
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'No current membership plan found'
                ], 404);
            }
            
            $allPlans = MembershipPlan::orderBy('price')->get();
            $recommendation = null;
            
            // Define plan hierarchy and recommendations
            $planHierarchy = [
                'Trial' => 'Pro',
                'Basic' => 'Pro', 
                'Pro' => 'Enterprise'
            ];
            
            $planBenefits = [
                'Pro' => [
                    'Unlimited products',
                    'Advanced analytics',
                    'Priority support',
                    'Custom integrations'
                ],
                'Enterprise' => [
                    'Everything in Pro',
                    'Dedicated account manager',
                    'Custom workflows',
                    'Advanced security features',
                    'API access'
                ]
            ];
            
            if (isset($planHierarchy[$currentPlan->name])) {
                $recommendedPlanName = $planHierarchy[$currentPlan->name];
                $recommendedPlan = $allPlans->firstWhere('name', $recommendedPlanName);
                
                if ($recommendedPlan) {
                    $recommendation = [
                        'type' => 'upgrade',
                        'current_plan' => [
                            'id' => $currentPlan->id,
                            'name' => $currentPlan->name,
                            'price' => $currentPlan->price
                        ],
                        'recommended_plan' => [
                            'id' => $recommendedPlan->id,
                            'name' => $recommendedPlan->name,
                            'price' => $recommendedPlan->price,
                            'description' => $recommendedPlan->description
                        ],
                        'benefits' => $planBenefits[$recommendedPlanName] ?? [],
                        'savings_message' => $this->calculateSavingsMessage($currentPlan, $recommendedPlan),
                        'upgrade_reason' => $this->getUpgradeReason($currentPlan->name, $recommendedPlanName)
                    ];
                }
            } else {
                // User is on the highest plan (Enterprise)
                $recommendation = [
                    'type' => 'best_plan',
                    'current_plan' => [
                        'id' => $currentPlan->id,
                        'name' => $currentPlan->name,
                        'price' => $currentPlan->price
                    ],
                    'message' => 'You\'re already on our best plan! Enjoy all premium features.',
                    'features' => [
                        'All premium features included',
                        'Dedicated support',
                        'Maximum usage limits',
                        'Priority access to new features'
                    ]
                ];
            }
            
            Log::info('Successfully generated plan recommendations', [
                'user_id' => $user->id,
                'recommendation_type' => $recommendation['type'] ?? 'none'
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $recommendation
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch plan recommendations', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plan recommendations'
            ], 500);
        }
    }
    
    /**
     * Calculate savings message for plan upgrade
     */
    private function calculateSavingsMessage($currentPlan, $recommendedPlan): string
    {
        $priceDifference = $recommendedPlan->price - $currentPlan->price;
        
        if ($priceDifference > 0) {
            return "Upgrade for just $" . number_format($priceDifference, 2) . " more per month";
        }
        
        return "Great value upgrade available";
    }
    
    /**
     * Get upgrade reason based on current and recommended plan
     */
    private function getUpgradeReason($currentPlanName, $recommendedPlanName): string
    {
        $reasons = [
            'Trial_to_Pro' => 'Unlock unlimited access and advanced features',
            'Basic_to_Pro' => 'Scale your business with unlimited products and analytics',
            'Pro_to_Enterprise' => 'Get enterprise-grade features and dedicated support'
        ];
        
        $key = $currentPlanName . '_to_' . $recommendedPlanName;
        return $reasons[$key] ?? 'Upgrade to access more powerful features';
    }

    /**
     * Upgrade user's membership plan
     */
    public function upgradePlan(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'plan_id' => 'required|exists:membership_plans,id'
            ]);
            
            /** @var User $user */
            $user = Auth::user();
            $newPlan = MembershipPlan::findOrFail($request->plan_id);
            $oldPlan = $user->membershipPlan;
            
            Log::info('User attempting to upgrade membership plan', [
                'user_id' => $user->id,
                'old_plan_id' => $oldPlan?->id,
                'old_plan_name' => $oldPlan?->name,
                'new_plan_id' => $newPlan->id,
                'new_plan_name' => $newPlan->name,
                'new_plan_price' => $newPlan->price
            ]);
            
            // Update user's membership plan
            $user->membership_plan_id = $newPlan->id;
            $user->save();
            
            // Reload the relationship
            $user->load('membershipPlan');
            
            Log::info('Successfully upgraded user membership plan', [
                'user_id' => $user->id,
                'new_plan_name' => $newPlan->name,
                'upgrade_timestamp' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Membership plan upgraded successfully',
                'data' => $user->membershipPlan
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upgrade membership plan', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'requested_plan_id' => $request->plan_id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upgrade membership plan'
            ], 500);
        }
    }
}