<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\MembershipPlanController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\StripePaymentController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EdamamNutritionController;
use App\Http\Controllers\EdamamFoodController;
use App\Http\Controllers\EdamamRecipeController;
use App\Http\Controllers\NutritionAutoTagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication Routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum', 'token.refresh'])->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all-devices', [AuthController::class, 'logoutFromAllDevices']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::delete('/auth/delete-account', [AuthController::class, 'deleteAccount']);
    Route::post('/auth/update-profile', [AuthController::class, 'updateProfile']);
});

// Password Reset Routes (OTP-based)
Route::prefix('password')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendPasswordResetOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/reset', [AuthController::class, 'resetPassword']);
});

// Legacy route for backward compatibility
Route::post('/password/email', [AuthController::class, 'sendPasswordResetOtp']);

// Public Routes
Route::get('/products/public', [ProductController::class, 'public']);
Route::get('/products/public/{id}', [ProductController::class, 'getPublicById']);
Route::get('/ingredients/search', [IngredientController::class, 'search']);

// Membership Plans Routes (public access for pricing page)
Route::get('/membership-plans', [MembershipPlanController::class, 'index']);
Route::get('/membership-plans/{id}', [MembershipPlanController::class, 'show']);


// Frontend Logging
Route::post('/logs/frontend', [App\Http\Controllers\Api\LogController::class, 'storeFrontendLog']);
Route::post('/logs/frontend/batch', [App\Http\Controllers\Api\LogController::class, 'storeBatchFrontendLogs']);

// Payment Routes (require authentication)
Route::middleware(['auth:sanctum', 'token.refresh'])->group(function () {
    Route::prefix('payment')->group(function () {
        Route::post('/create-intent', [StripePaymentController::class, 'createPaymentIntent']);
        Route::get('/status', [StripePaymentController::class, 'getPaymentStatus']);
        
        // Subscription Cancellation Routes (3-day waiting period system)
        Route::post('/request-cancellation', [StripePaymentController::class, 'requestCancellation']);
        Route::post('/confirm-cancellation', [StripePaymentController::class, 'confirmCancellation']);
        Route::post('/cancel-cancellation-request', [StripePaymentController::class, 'cancelCancellationRequest']);
        Route::get('/cancellation-status', [StripePaymentController::class, 'getCancellationStatus']);
        
        Route::post('/auto-renew', [StripePaymentController::class, 'updateAutoRenew']);
        Route::get('/subscription', [StripePaymentController::class, 'getSubscriptionDetails']);
        Route::post('/update-method', [StripePaymentController::class, 'updatePaymentMethod']);

});



// User-specific membership plan routes (require authentication)
Route::middleware(['auth:sanctum', 'token.refresh'])->group(function () {
    Route::get('/user/membership-plan', [MembershipPlanController::class, 'getCurrentPlan']);
    Route::get('/user/plan-recommendations', [MembershipPlanController::class, 'getRecommendations']);
    Route::post('/user/upgrade-plan', [MembershipPlanController::class, 'upgradePlan']);
});

// Protected Routes (require authentication and dashboard access)
Route::middleware(['auth:sanctum', 'token.refresh', 'dashboard.access'])->group(function () {
    // Product-specific routes (must come before apiResource)
    Route::post('/products/metrics', [ProductController::class, 'getMetrics']);
    Route::post('/products/convert-units', [ProductController::class, 'convertUnits']);
    Route::post('/products/suggest-unit', [ProductController::class, 'suggestUnit']);
    Route::post('/products/extract-image-url', [ProductController::class, 'extractImageUrl']);
    Route::post('/products/{id}/duplicate', [ProductController::class, 'duplicate']);
    Route::patch('/products/{id}/toggle-pin', [ProductController::class, 'togglePin']);
    Route::patch('/products/{id}/toggle-favorite', [ProductController::class, 'toggleFavorite']);
    Route::get('/products/favorites/list', [ProductController::class, 'getFavorites']);
    Route::get('/products/categories/list', [ProductController::class, 'getCategories']);
    Route::get('/products/tags/list', [ProductController::class, 'getTags']);
    Route::get('/products/trashed/list', [ProductController::class, 'trashed']);
    Route::patch('/products/{id}/restore', [ProductController::class, 'restore']);
    Route::delete('/products/{id}/force-delete', [ProductController::class, 'forceDelete']);
    
    // Product Management (apiResource)
    Route::apiResource('products', ProductController::class);
    
    // Category Management
    Route::get('/categories/search', [CategoryController::class, 'search']);
    Route::apiResource('categories', CategoryController::class);
    
    // Collection Management
    Route::prefix('collections')->group(function () {
        Route::get('/', [\App\Http\Controllers\CollectionController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\CollectionController::class, 'store']);
        Route::get('/{collection}', [\App\Http\Controllers\CollectionController::class, 'show']);
        Route::put('/{collection}', [\App\Http\Controllers\CollectionController::class, 'update']);
        Route::delete('/{collection}', [\App\Http\Controllers\CollectionController::class, 'destroy']);
        Route::post('/{collection}/products', [\App\Http\Controllers\CollectionController::class, 'addProduct']);
        Route::delete('/{collection}/products/{product}', [\App\Http\Controllers\CollectionController::class, 'removeProduct']);
        Route::get('/{collection}/products', [\App\Http\Controllers\CollectionController::class, 'getProducts']);
    });
    
    // Ingredient Management
    Route::apiResource('ingredients', IngredientController::class);
    
    // Usage Tracking
    Route::get('/user/usage', [UsageController::class, 'getCurrentUsage']);
    Route::post('/user/check-permission', [UsageController::class, 'checkPermission']);
    
    // Billing Management
    Route::prefix('billing')->group(function () {
        Route::get('/information', [BillingController::class, 'getBillingInformation']);
        Route::post('/information', [BillingController::class, 'saveBillingInformation']);
        Route::get('/payment-methods', [BillingController::class, 'getPaymentMethods']);
        Route::post('/payment-methods', [BillingController::class, 'addPaymentMethod']);
        Route::get('/history', [BillingController::class, 'getBillingHistory']);
        Route::get('/invoice/{invoiceId}/download', [BillingController::class, 'downloadInvoice']);
        Route::get('/history/export', [BillingController::class, 'exportBillingHistory']);
        Route::post('/test-data', [BillingController::class, 'createTestBillingHistory']);
    });
    
    // User Settings Management
    Route::prefix('user')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::post('/settings/reset', [SettingsController::class, 'reset']);
        Route::get('/settings/options', [SettingsController::class, 'options']);
    });
    
    // Nutrition Auto Tags Routes
    Route::post('/nutrition/auto-tags/save', [NutritionAutoTagController::class, 'saveAutoTags']);
    
    // Nutrition Data Routes
    Route::prefix('nutrition')->group(function () {
        Route::post('/save-data', [EdamamNutritionController::class, 'saveNutritionData'])
            ->middleware('throttle:nutrition_analysis');
        Route::post('/check-data', [EdamamNutritionController::class, 'checkNutritionData']);
        Route::post('/load-data', [EdamamNutritionController::class, 'loadNutritionData']);
    });
    
    // Other protected routes will be added here
});



// Edamam API Routes (require authentication and dashboard access)
Route::middleware(['auth:sanctum', 'token.refresh', 'dashboard.access', 'edamam.api'])->prefix('edamam')->group(function () {
    // Nutrition Analysis Routes
    Route::prefix('nutrition')->group(function () {
        Route::post('/analyze', [EdamamNutritionController::class, 'analyze'])
            ->middleware('throttle:nutrition_analysis');
        Route::post('/batch-analyze', [EdamamNutritionController::class, 'batchAnalyze'])
            ->middleware('throttle:nutrition_batch');
        Route::post('/save', [EdamamNutritionController::class, 'saveNutritionData'])
            ->middleware('throttle:nutrition_analysis');
        Route::get('/history', [EdamamNutritionController::class, 'history']);
        Route::delete('/cache', [EdamamNutritionController::class, 'clearCache']);
    });
    
    // Food Database Routes
    Route::prefix('food')->group(function () {
        Route::get('/autocomplete', [EdamamFoodController::class, 'autocomplete'])
            ->middleware('throttle:food_autocomplete');
        Route::get('/search', [EdamamFoodController::class, 'search'])
            ->middleware('throttle:food_search');
        Route::get('/upc/{upc}', [EdamamFoodController::class, 'getByUpc'])
            ->middleware('throttle:food_upc');
        Route::get('/popular', [EdamamFoodController::class, 'popular']);
        Route::get('/categories', [EdamamFoodController::class, 'categories']);
        Route::delete('/cache', [EdamamFoodController::class, 'clearCache']);
    });
    
    // Recipe Search Routes
    Route::prefix('recipes')->group(function () {
        Route::get('/search', [EdamamRecipeController::class, 'search'])
            ->middleware('throttle:recipe_search');
        Route::get('/show', [EdamamRecipeController::class, 'show'])
            ->middleware('throttle:recipe_details');
        Route::get('/random', [EdamamRecipeController::class, 'random'])
            ->middleware('throttle:recipe_random');
        Route::get('/suggest', [EdamamRecipeController::class, 'suggest'])
            ->middleware('throttle:recipe_suggest');
        Route::post('/generate-ingredients', [EdamamRecipeController::class, 'generateIngredients'])
            ->middleware('throttle:recipe_search');
        Route::get('/filters', [EdamamRecipeController::class, 'filters']);
        Route::delete('/cache', [EdamamRecipeController::class, 'clearCache']);
    });
});

    // Ingredients Generation Route (alias for easier access)
    Route::post('/ingredients/generate', [EdamamRecipeController::class, 'generateIngredients'])
        ->middleware('throttle:recipe_search');
});