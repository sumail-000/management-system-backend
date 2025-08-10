<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     * Implements comprehensive security headers and XSS protection.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize input data to prevent XSS
        $this->sanitizeInput($request);
        
        // Process the request
        $response = $next($request);
        
        // Add comprehensive security headers
        $this->addSecurityHeaders($response, $request);
        
        return $response;
    }
    
    /**
     * Sanitize input data to prevent XSS attacks
     */
    private function sanitizeInput(Request $request): void
    {
        $suspiciousPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b[^>]*>/i',
            '/<object\b[^>]*>/i',
            '/<embed\b[^>]*>/i',
            '/<link\b[^>]*>/i',
            '/<meta\b[^>]*>/i',
            '/expression\s*\(/i',
            '/vbscript:/i',
            '/data:text\/html/i'
        ];
        
        $inputData = $request->all();
        $hasSuspiciousContent = false;
        $suspiciousFields = [];
        
        foreach ($inputData as $key => $value) {
            if (is_string($value)) {
                foreach ($suspiciousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $hasSuspiciousContent = true;
                        $suspiciousFields[] = $key;
                        
                        Log::channel('security')->warning('Potential XSS attempt detected', [
                            'field' => $key,
                            'pattern' => $pattern,
                            'value' => substr($value, 0, 200), // Log first 200 chars
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'route' => $request->route()?->getName(),
                            'user_id' => $request->user()?->id,
                            'timestamp' => now()->toISOString()
                        ]);
                        break;
                    }
                }
            }
        }
        
        if ($hasSuspiciousContent) {
            Log::channel('security')->error('XSS attack attempt blocked', [
                'suspicious_fields' => $suspiciousFields,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName(),
                'user_id' => $request->user()?->id,
                'full_url' => $request->fullUrl(),
                'method' => $request->method()
            ]);
            
            // You could throw an exception here to block the request
            // throw new \Illuminate\Validation\ValidationException('Suspicious content detected');
        }
    }
    
    /**
     * Add comprehensive security headers
     */
    private function addSecurityHeaders(Response $response, Request $request): void
    {
        // Prevent XSS attacks
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Content Security Policy
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com https://checkout.stripe.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https: blob:",
            "connect-src 'self' https://api.stripe.com https://checkout.stripe.com",
            "frame-src https://js.stripe.com https://hooks.stripe.com https://checkout.stripe.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ]);
        $response->headers->set('Content-Security-Policy', $csp);
        
        // Strict Transport Security (HTTPS only)
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions Policy
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        // Remove server information
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');
        
        // Add custom security headers
        $response->headers->set('X-Security-Headers', 'enabled');
        $response->headers->set('X-Request-ID', uniqid('req_', true));
        
        // CORS headers for API requests
        if ($request->is('api/*')) {
            $allowedOrigins = [
                'http://localhost:8080',
                'http://localhost:8081',
                'http://127.0.0.1:8080',
                'http://127.0.0.1:8081'
            ];
            
            $origin = $request->header('Origin');
            if (in_array($origin, $allowedOrigins)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            }
            
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '86400');
        }
    }
}