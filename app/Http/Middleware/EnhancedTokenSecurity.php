<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnhancedTokenSecurity
{
    /**
     * Handle an incoming request.
     * Implements advanced token security measures including rate limiting,
     * suspicious activity detection, and token integrity validation.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return $next($request);
        }
        
        $token = $user->currentAccessToken();
        
        if (!$token) {
            return $next($request);
        }
        
        // Check for suspicious token usage patterns
        $this->detectSuspiciousActivity($request, $user, $token);
        
        // Validate token integrity
        $this->validateTokenIntegrity($request, $user, $token);
        
        // Implement rate limiting per token
        $this->enforceRateLimit($request, $user, $token);
        
        // Log token usage for audit trail
        $this->logTokenUsage($request, $user, $token);
        
        return $next($request);
    }
    
    /**
     * Detect suspicious token usage patterns
     */
    private function detectSuspiciousActivity(Request $request, $user, $token): void
    {
        $tokenId = $token->id;
        $currentIp = $request->ip();
        $currentUserAgent = $request->userAgent();
        
        // Check for IP address changes
        $lastIpKey = "token_last_ip_{$tokenId}";
        $lastIp = Cache::get($lastIpKey);
        
        if ($lastIp && $lastIp !== $currentIp) {
            Log::channel('security')->warning('Token used from different IP address', [
                'user_id' => $user->id,
                'token_id' => $tokenId,
                'previous_ip' => $lastIp,
                'current_ip' => $currentIp,
                'user_agent' => $currentUserAgent,
                'route' => $request->route()?->getName()
            ]);
            
            // Store IP change event
            $ipChanges = Cache::get("token_ip_changes_{$tokenId}", []);
            $ipChanges[] = [
                'from' => $lastIp,
                'to' => $currentIp,
                'timestamp' => now()->toISOString(),
                'user_agent' => $currentUserAgent
            ];
            
            // Keep only last 10 IP changes
            $ipChanges = array_slice($ipChanges, -10);
            Cache::put("token_ip_changes_{$tokenId}", $ipChanges, 3600);
            
            // If too many IP changes, flag as suspicious
            if (count($ipChanges) > 5) {
                Log::channel('security')->error('Suspicious token activity: Multiple IP changes', [
                    'user_id' => $user->id,
                    'token_id' => $tokenId,
                    'ip_changes' => $ipChanges,
                    'current_request' => [
                        'ip' => $currentIp,
                        'user_agent' => $currentUserAgent,
                        'route' => $request->route()?->getName()
                    ]
                ]);
            }
        }
        
        Cache::put($lastIpKey, $currentIp, 3600);
        
        // Check for user agent changes
        $lastUserAgentKey = "token_last_ua_{$tokenId}";
        $lastUserAgent = Cache::get($lastUserAgentKey);
        
        if ($lastUserAgent && $lastUserAgent !== $currentUserAgent) {
            Log::channel('security')->warning('Token used with different user agent', [
                'user_id' => $user->id,
                'token_id' => $tokenId,
                'previous_user_agent' => $lastUserAgent,
                'current_user_agent' => $currentUserAgent,
                'ip_address' => $currentIp
            ]);
        }
        
        Cache::put($lastUserAgentKey, $currentUserAgent, 3600);
    }
    
    /**
     * Validate token integrity and detect tampering
     */
    private function validateTokenIntegrity(Request $request, $user, $token): void
    {
        // Check if token was created recently but has suspicious usage patterns
        $tokenAge = now()->diffInMinutes($token->created_at);
        $usageCountKey = "token_usage_count_{$token->id}";
        
        // Get current count and increment, setting TTL if key doesn't exist
        $usageCount = Cache::get($usageCountKey, 0) + 1;
        Cache::put($usageCountKey, $usageCount, 3600);
        
        // If token is very new but has high usage, flag as suspicious
        if ($tokenAge < 5 && $usageCount > 50) {
            Log::channel('security')->warning('Suspicious token usage: High frequency on new token', [
                'user_id' => $user->id,
                'token_id' => $token->id,
                'token_age_minutes' => $tokenAge,
                'usage_count' => $usageCount,
                'ip_address' => $request->ip()
            ]);
        }
        
        // Check token expiration
        $expirationTime = $token->created_at->addMinutes((int) config('sanctum.expiration', 1440));
        if (now()->isAfter($expirationTime)) {
            Log::channel('security')->warning('Expired token usage attempt', [
                'user_id' => $user->id,
                'token_id' => $token->id,
                'expired_at' => $expirationTime->toISOString(),
                'ip_address' => $request->ip()
            ]);
        }
    }
    
    /**
     * Enforce rate limiting per token
     */
    private function enforceRateLimit(Request $request, $user, $token): void
    {
        $rateLimitKey = "token_rate_limit_{$token->id}";
        $requestCount = Cache::get($rateLimitKey, 0);
        
        // Allow 1000 requests per hour per token
        if ($requestCount > 1000) {
            Log::channel('security')->error('Token rate limit exceeded', [
                'user_id' => $user->id,
                'token_id' => $token->id,
                'request_count' => $requestCount,
                'ip_address' => $request->ip(),
                'route' => $request->route()?->getName()
            ]);
            
            // You could throw an exception here to block the request
            // throw new \Illuminate\Http\Exceptions\ThrottleRequestsException('Rate limit exceeded');
        }
        
        Cache::put($rateLimitKey, $requestCount + 1, 3600);
    }
    
    /**
     * Log token usage for audit trail
     */
    private function logTokenUsage(Request $request, $user, $token): void
    {
        // Only log sensitive routes or admin access
        $sensitiveRoutes = ['admin.*', 'api.admin.*', '*.delete', '*.update'];
        $currentRoute = $request->route()?->getName();
        
        $isSensitive = false;
        foreach ($sensitiveRoutes as $pattern) {
            if (fnmatch($pattern, $currentRoute)) {
                $isSensitive = true;
                break;
            }
        }
        
        // Check if user is admin (from separate admin table)
        $isAdmin = Auth::guard('admin')->check();
        
        if ($isSensitive || $isAdmin) {
            Log::channel('audit')->info('Token usage audit', [
                'user_id' => $user->id,
                'email' => $user->email,
                'user_type' => $isAdmin ? 'admin' : 'user',
                'token_id' => $token->id,
                'route' => $currentRoute,
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);
        }
    }
}