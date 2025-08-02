<?php

use Illuminate\Support\Facades\Route;

// Basic login route to prevent 500 errors when auth middleware redirects
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

// Serve React frontend for all non-API routes
Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '^(?!api).*$');
