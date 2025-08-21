<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateLastActiveAt
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
        // The auth:sanctum middleware has already run and identified the user.
        // We can get the authenticated user (either User or Admin model) directly.
        $user = $request->user();

        if ($user) {
            // To reduce database writes, only update the timestamp if it's older than a minute.
            if ($user->last_active_at === null || $user->last_active_at->diffInMinutes(now()) >= 1) {
                $user->last_active_at = now();
                $user->save();
            }
        }

        return $next($request);
    }
}
