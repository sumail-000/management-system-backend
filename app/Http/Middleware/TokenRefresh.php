<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TokenRefresh
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
        // Only process if user is authenticated via Sanctum
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            $token = $user->currentAccessToken();
            
            if ($token) {
                // Check if token is close to expiration (within 2 hours)
                $expirationTime = $token->created_at->addMinutes((int) config('sanctum.expiration', 1440));
                $hoursUntilExpiration = now()->diffInHours($expirationTime, false);
                
                // If token expires within 2 hours, refresh it
                if ($hoursUntilExpiration <= 2 && $hoursUntilExpiration > 0) {
                    Log::info('Token refresh triggered', [
                        'user_id' => $user->id,
                        'token_id' => $token->id,
                        'hours_until_expiration' => $hoursUntilExpiration
                    ]);
                    
                    // Delete old token
                    $token->delete();
                    
                    // Create new token
                    $newToken = $user->createToken('auth_token');
                    
                    // Add new token to response headers
                    $response = $next($request);
                    $response->headers->set('X-New-Token', $newToken->plainTextToken);
                    $response->headers->set('X-Token-Refreshed', 'true');
                    
                    Log::info('Token refreshed successfully', [
                        'user_id' => $user->id,
                        'new_token_id' => $newToken->accessToken->id
                    ]);
                    
                    return $response;
                }
            }
        }
        
        return $next($request);
    }
}