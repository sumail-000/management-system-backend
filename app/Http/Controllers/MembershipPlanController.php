<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
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
            

            
            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
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
            
            return response()->json([
                'success' => true,
                'data' => $user->membershipPlan
            ]);
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
            
            // Check if user can upgrade
            if (!$user->canUpgradePlan()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot upgrade your plan at this time. Please ensure you have an active subscription or trial.'
                ], 400);
            }
            
            // Check if user is trying to "upgrade" to the same plan
            if ($user->membership_plan_id === $newPlan->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already subscribed to the ' . $newPlan->name . ' plan.'
                ], 400);
            }
            
            Log::info('User attempting to upgrade membership plan', [
                'user_id' => $user->id,
                'old_plan_id' => $oldPlan?->id,
                'old_plan_name' => $oldPlan?->name,
                'new_plan_id' => $newPlan->id,
                'new_plan_name' => $newPlan->name,
                'new_plan_price' => $newPlan->price,
                'current_payment_status' => $user->payment_status
            ]);
            
            // Update user's membership plan
            $user->membership_plan_id = $newPlan->id;
            
            // If user had cancelled subscription, reactivate it for the new plan
            if ($user->payment_status === 'cancelled') {
                $user->payment_status = 'paid';
                $user->auto_renew = true;
                $user->cancelled_at = null;
                
                Log::info('Reactivated cancelled subscription due to plan upgrade', [
                    'user_id' => $user->id,
                    'new_plan_name' => $newPlan->name
                ]);
            }
            
            $user->save();
            
            // Reload the relationship
            $user->load('membershipPlan');
            
            Log::info('Successfully upgraded user membership plan', [
                'user_id' => $user->id,
                'new_plan_name' => $newPlan->name,
                'upgrade_timestamp' => now(),
                'payment_status' => $user->payment_status
            ]);
            
            $message = 'Membership plan upgraded successfully to ' . $newPlan->name;
            if ($user->payment_status === 'paid' && $user->hasCancelledSubscription()) {
                $message .= ' Your subscription has been reactivated and will auto-renew.';
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $user->membershipPlan,
                'payment_status' => $user->payment_status,
                'auto_renew' => $user->auto_renew
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