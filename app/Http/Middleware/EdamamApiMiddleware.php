<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EdamamApiMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Generate request ID for tracking
        $requestId = 'edamam_' . Str::uuid();
        $request->headers->set('X-Request-ID', $requestId);
        
        // Log API request
        Log::info('Edamam API Request', [
            'request_id' => $requestId,
            'user_id' => $request->user()?->id,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'params' => $this->sanitizeParams($request->all())
        ]);
        
        // Validate content type for POST requests
        if ($request->isMethod('POST') && !$request->isJson() && !$request->hasHeader('Content-Type')) {
            return response()->json([
                'error' => 'Invalid Content-Type',
                'message' => 'Content-Type must be application/json for POST requests',
                'request_id' => $requestId
            ], 400);
        }
        
        // Add security headers
        $response = $next($request);
        
        if ($response instanceof Response) {
            $response->headers->set('X-Request-ID', $requestId);
            $response->headers->set('X-API-Version', '1.0');
            $response->headers->set('X-RateLimit-Remaining', $this->getRateLimitRemaining($request));
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
        
        // Log API response
        Log::info('Edamam API Response', [
            'request_id' => $requestId,
            'status_code' => $response->getStatusCode(),
            'response_time' => microtime(true) - LARAVEL_START
        ]);
        
        return $response;
    }
    
    /**
     * Sanitize request parameters for logging
     *
     * @param array $params
     * @return array
     */
    private function sanitizeParams(array $params): array
    {
        $sanitized = [];
        
        foreach ($params as $key => $value) {
            // Don't log sensitive information
            if (in_array(strtolower($key), ['password', 'token', 'secret', 'key'])) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeParams($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get remaining rate limit for the current request
     *
     * @param Request $request
     * @return string
     */
    private function getRateLimitRemaining(Request $request): string
    {
        // This would typically integrate with your rate limiting system
        // For now, return a placeholder
        return '100';
    }
}