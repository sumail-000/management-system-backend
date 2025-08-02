<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckDashboardAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            Log::channel('auth')->warning('Unauthenticated user attempted dashboard access', [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName()
            ]);
            
            return response()->json([
                'message' => 'Unauthenticated',
                'requires_login' => true
            ], 401);
        }
        
        // Check if trial has expired and update status
        if ($user->isTrialExpired()) {
            $user->update(['payment_status' => 'expired']);
            Log::channel('auth')->warning('User trial expired during dashboard access', [
                'user_id' => $user->id,
                'trial_ended_at' => $user->trial_ends_at,
                'route' => $request->route()?->getName()
            ]);
        }
        
        // Check if user can access dashboard
        if (!$user->canAccessDashboard()) {
            Log::channel('auth')->warning('User denied dashboard access - payment required', [
                'user_id' => $user->id,
                'payment_status' => $user->payment_status,
                'membership_plan' => $user->membershipPlan?->name,
                'route' => $request->route()?->getName()
            ]);
            
            $responseData = [
                'message' => 'Payment required to access dashboard',
                'requires_payment' => true,
                'payment_status' => $user->payment_status,
                'membership_plan' => $user->membershipPlan?->name
            ];
            
            // Add trial info if applicable
            if ($user->payment_status === 'expired' && $user->trial_ends_at) {
                $responseData['trial_info'] = [
                    'trial_expired' => true,
                    'trial_ended_at' => $user->trial_ends_at
                ];
            }
            
            return response()->json($responseData, 402); // Payment Required
        }
        

        
        return $next($request);
    }
}