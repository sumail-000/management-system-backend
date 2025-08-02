<?php

use Illuminate\Support\Facades\Route;

// Basic login route to prevent 500 errors when auth middleware redirects
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

