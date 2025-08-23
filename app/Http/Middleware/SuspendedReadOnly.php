<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SuspendedReadOnly
{
    /**
     * Handle an incoming request.
     * If the authenticated user is suspended, restrict to read-only actions,
     * allowing only GET/HEAD/OPTIONS requests and explicit exceptions (logout endpoints).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if (!$user->is_suspended) {
            return $next($request);
        }

        // Allow non-mutating requests
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // Explicit endpoint exceptions allowed while suspended
        // Allow logout endpoints
        if ($request->is('api/auth/logout') || $request->is('api/auth/logout-all-devices')) {
            return $next($request);
        }

        Log::channel('security')->warning('Blocked write action for suspended user', [
            'user_id' => $user->id,
            'email' => $user->email,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Your account is currently suspended. Dashboard is read-only. You can still view content and access support.',
            'error_code' => 'ACCOUNT_SUSPENDED_READ_ONLY'
        ], 423); // 423 Locked
    }
}
