<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Enable CORS middleware
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        $middleware->alias([
            'dashboard.access' => \App\Http\Middleware\CheckDashboardAccess::class,
            'token.refresh' => \App\Http\Middleware\TokenRefresh::class,
            'edamam.api' => \App\Http\Middleware\EdamamApiMiddleware::class,
        ]);
        
        // Configure authentication to return JSON responses for API routes
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null; // Don't redirect, let it return 401
            }
            return route('login'); // This will still fail but won't be called for API routes
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
