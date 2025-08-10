<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminRoleGuard
{
    /**
     * Handle an incoming request.
     * Provides robust protection against role manipulation and unauthorized admin access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Get authenticated user from sanctum
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            Log::channel('security')->warning('Unauthenticated admin access attempt', [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName(),
                'url' => $request->fullUrl(),
                'timestamp' => now()->toISOString()
            ]);
            
            return response()->json([
                'message' => 'Authentication required',
                'error' => 'UNAUTHORIZED_ACCESS'
            ], 401);
        }
        
        // Check if the authenticated user is actually an admin
        // For admin authentication, we need to check if this user exists in the admins table
        $admin = null;
        
        // First, check if this is an Admin model instance
        if ($user instanceof \App\Models\Admin) {
            $admin = $user;
        } else {
            // If it's a User model, check if there's a corresponding admin record by email
            $admin = \App\Models\Admin::where('email', $user->email)->first();
        }
        
        if (!$admin) {
            Log::channel('security')->warning('Non-admin user attempted admin access', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_type' => get_class($user),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName(),
                'url' => $request->fullUrl(),
                'timestamp' => now()->toISOString()
            ]);
            
            return response()->json([
                'message' => 'Admin access required',
                'error' => 'INSUFFICIENT_PRIVILEGES'
            ], 403);
        }
        
        // Check if admin account is active
        if (!$admin->is_active) {
            Log::channel('security')->warning('Inactive admin attempted access', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName()
            ]);
            
            return response()->json([
                'message' => 'Admin account is deactivated',
                'error' => 'ACCOUNT_DEACTIVATED'
            ], 403);
        }

        // Check IP restriction if enabled
        $currentIp = $request->ip();
        if (!$admin->isIpAllowed($currentIp)) {
            Log::channel('security')->warning('Admin access denied due to IP restriction', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'ip_address' => $currentIp,
                'allowed_ips' => $admin->allowed_ips,
                'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName(),
                'url' => $request->fullUrl(),
                'timestamp' => now()->toISOString()
            ]);
            
            return response()->json([
                'message' => 'Access denied. Your IP address is not authorized for admin access.',
                'error_code' => 'IP_RESTRICTION_VIOLATION'
            ], 403);
        }
        
        // Verify token integrity and expiration
        $token = $user->currentAccessToken();
        if (!$token) {
            Log::channel('security')->warning('Admin access attempt without valid token', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Invalid authentication token',
                'error' => 'INVALID_TOKEN'
            ], 401);
        }
        
        // Check token expiration
        $expirationTime = $token->created_at->addMinutes((int) config('sanctum.expiration', 1440));
        if (now()->isAfter($expirationTime)) {
            Log::channel('security')->warning('Admin access attempt with expired token', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token_created_at' => $token->created_at,
                'token_expired_at' => $expirationTime,
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Authentication token expired',
                'error' => 'TOKEN_EXPIRED'
            ], 401);
        }
        
        // Log successful admin access
        Log::channel('security')->info('Admin access granted', [
            'admin_id' => $admin->id,
            'email' => $admin->email,
            'role' => $admin->role,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ]);
        
        // Add security headers
        $response = $next($request);
        
        $response->headers->set('X-Admin-Access', 'true');
        $response->headers->set('X-Admin-Role', $admin->role);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        return $response;
    }
}