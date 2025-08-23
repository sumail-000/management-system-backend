<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\CustomIngredientController;
use App\Http\Controllers\Api\MembershipPlanController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\StripePaymentController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\FoodController;
use App\Http\Controllers\NutritionController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Api\TeamMemberController;

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
Route::middleware(['auth:sanctum', 'token.refresh', 'enhanced.token.security', 'update.activity'])->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all-devices', [AuthController::class, 'logoutFromAllDevices']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::post('/auth/request-account-deletion', [AuthController::class, 'requestAccountDeletion']);
    Route::post('/auth/cancel-account-deletion', [AuthController::class, 'cancelAccountDeletion']);
    Route::get('/auth/account-deletion-status', [AuthController::class, 'getAccountDeletionStatus']);
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

// Team Member Authentication (separate from user)
Route::post('/team-members/login', [TeamMemberController::class, 'login']);


// QR Code public routes
Route::post('/qr-codes/{qrCodeId}/scan', [QrCodeController::class, 'trackScan'])->name('api.qr-codes.scan');

// Membership Plans Routes (public access for pricing page)
Route::get('/membership-plans', [MembershipPlanController::class, 'index']);
Route::get('/membership-plans/{id}', [MembershipPlanController::class, 'show']);


// Frontend Logging
Route::post('/logs/frontend', [App\Http\Controllers\Api\LogController::class, 'storeFrontendLog']);
Route::post('/logs/frontend/batch', [App\Http\Controllers\Api\LogController::class, 'storeBatchFrontendLogs']);

// Payment Routes (require authentication)
Route::middleware(['auth:sanctum', 'token.refresh', 'enhanced.token.security'])->group(function () {
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
});



// User-specific membership plan routes (require authentication)
Route::middleware(['auth:sanctum', 'token.refresh', 'enhanced.token.security'])->group(function () {
    Route::get('/user/membership-plan', [MembershipPlanController::class, 'getCurrentPlan']);
    Route::get('/user/plan-recommendations', [MembershipPlanController::class, 'getRecommendations']);
    Route::post('/user/upgrade-plan', [MembershipPlanController::class, 'upgradePlan']);
});

// Protected Routes (require authentication and dashboard access)
Route::middleware(['auth:sanctum', 'token.refresh', 'enhanced.token.security', 'dashboard.access'])->group(function () {
    // Progressive Recipe Creation Routes (must come before apiResource)
    Route::prefix('products/{id}')->group(function () {
        Route::post('/details', [ProductController::class, 'saveProductDetails']);
        Route::post('/upload-image', [ProductController::class, 'uploadImage']);
        Route::post('/ingredients', [ProductController::class, 'addIngredients']);
        Route::post('/clear-ingredients', [ProductController::class, 'clearIngredients']);
        Route::post('/nutrition', [ProductController::class, 'saveNutritionData']);
        Route::post('/serving', [ProductController::class, 'configureServing']);
        Route::post('/ingredient-statements', [ProductController::class, 'saveIngredientStatements']);
        Route::post('/allergens', [ProductController::class, 'saveAllergens']);
        Route::post('/complete', [ProductController::class, 'completeRecipe']);
        Route::get('/progress', [ProductController::class, 'getProgress']);
        
        // Individual ingredient management
        Route::put('/ingredients/{ingredientId}', [ProductController::class, 'updateIngredient']);
        Route::delete('/ingredients/{ingredientId}', [ProductController::class, 'removeIngredient']);
    });
    
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
    Route::get('/products/{id}/tags', [ProductController::class, 'getProductTags']);
    Route::get('/products/trashed/list', [ProductController::class, 'trashed']);
    Route::post('/products/bulk-delete', [ProductController::class, 'bulkDelete']);
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
    
    // Team Members Management (Enterprise owner only)
    Route::apiResource('team-members', TeamMemberController::class)->except(['show']);

    // User Settings Management
    Route::prefix('user')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::post('/settings/reset', [SettingsController::class, 'reset']);
        Route::get('/settings/options', [SettingsController::class, 'options']);

        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::post('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'store']);
        Route::patch('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markRead']);
        Route::patch('/notifications/mark-all-read', [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);
    });
    
    // QR Code Management Routes
    Route::prefix('qr-codes')->name('api.qr-codes.')->group(function () {
        Route::get('/', [QrCodeController::class, 'index'])->name('index');
        Route::post('/products/{productId}/generate', [QrCodeController::class, 'generate'])->name('generate');
        Route::post('/generate-from-url', [QrCodeController::class, 'generateFromUrl'])->name('generate-from-url');
        Route::get('/products/{productId}', [QrCodeController::class, 'show'])->name('show');
        Route::get('/{qrCodeId}/download', [QrCodeController::class, 'download'])->name('download');
        Route::delete('/{qrCodeId}', [QrCodeController::class, 'destroy'])->name('destroy');
        Route::get('/analytics', [QrCodeController::class, 'analytics'])->name('analytics');
        Route::get('/creation-deletion-analytics', [QrCodeController::class, 'getAnalytics'])->name('creation-deletion-analytics');
        Route::get('/{qrCodeId}/analytics', [QrCodeController::class, 'qrCodeAnalytics'])->name('qr-analytics');
    });
    
    // Food Database API Routes (Edamam Integration)
    Route::prefix('food')->group(function () {
        Route::post('/search-recipes', [FoodController::class, 'searchRecipes']);
        Route::post('/search-food-database', [FoodController::class, 'searchFoodDatabase']);
        Route::post('/parse-ingredient', [FoodController::class, 'parseIngredient']);
        Route::post('/nutrients', [FoodController::class, 'getFoodNutrients']);
    });
    
    // Nutrition Analysis API Routes (Edamam Integration)
    Route::prefix('nutrition')->group(function () {
        Route::post('/analyze', [NutritionController::class, 'analyzeNutrition']);
        Route::post('/build-ingredient-string', [NutritionController::class, 'buildIngredientString']);
    });
    
    // Custom Ingredients Management
    Route::prefix('custom-ingredients')->group(function () {
        Route::get('/', [CustomIngredientController::class, 'index']);
        Route::post('/', [CustomIngredientController::class, 'store']);
        Route::get('/search', [CustomIngredientController::class, 'search']);
        Route::get('/categories', [CustomIngredientController::class, 'getCategories']);
        Route::get('/{id}', [CustomIngredientController::class, 'show']);
        Route::put('/{id}', [CustomIngredientController::class, 'update']);
        Route::delete('/{id}', [CustomIngredientController::class, 'destroy']);
        Route::get('/{id}/usage', [CustomIngredientController::class, 'getUsage']);
        Route::post('/{id}/increment-usage', [CustomIngredientController::class, 'incrementUsage']);
    });

    // Label generation logging
    Route::post('/labels/log-generation', [App\Http\Controllers\Api\LabelController::class, 'logGeneration']);

    // Support Center
    Route::prefix('support')->group(function () {
        Route::get('/tickets', [App\Http\Controllers\Api\SupportController::class, 'listTickets']);
        Route::post('/tickets/start', [App\Http\Controllers\Api\SupportController::class, 'startTicket']);
        Route::post('/tickets/{id}/finalize', [App\Http\Controllers\Api\SupportController::class, 'finalizeTicket']);
        Route::delete('/tickets/{id}/cancel', [App\Http\Controllers\Api\SupportController::class, 'cancelTicket']);
        Route::get('/faqs', [App\Http\Controllers\Api\SupportController::class, 'listFaqs']);
        Route::get('/tickets/{id}', [App\Http\Controllers\Api\SupportController::class, 'getTicket']);
        Route::post('/tickets/{id}/messages', [App\Http\Controllers\Api\SupportController::class, 'addMessage']);
    });
    // Other protected routes will be added here
});

// Admin Routes (require admin role and enhanced security)
Route::prefix('admin')->middleware(['auth:sanctum,admin', 'enhanced.token.security', 'admin.role.guard', 'update.activity'])->group(function () {
    // Admin Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/metrics', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getMetrics']);
        Route::get('/analytics', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getAnalytics']);
        Route::get('/system-health', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getSystemHealth']);
        Route::get('/recent-activities', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getRecentActivities']);
        Route::get('/subscription-distribution', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getSubscriptionDistribution']);
        Route::get('/user-growth', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getUserGrowth']);
        Route::get('/api-usage-by-plan', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getApiUsageByPlan']);
        Route::get('/feature-usage', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getFeatureUsage']);
    });
    
    // Admin Profile
    Route::prefix('profile')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\ProfileController::class, 'show']);
        Route::put('/', [\App\Http\Controllers\Api\Admin\ProfileController::class, 'update']);
        Route::post('/avatar', [\App\Http\Controllers\Api\Admin\ProfileController::class, 'updateAvatar']);
        Route::post('/password', [\App\Http\Controllers\Api\Admin\ProfileController::class, 'updatePassword']);
        Route::get('/permissions', [\App\Http\Controllers\Api\Admin\ProfileController::class, 'getPermissions']);
        Route::get('/activity', [\App\Http\Controllers\Api\Admin\ProfileController::class, 'getRecentActivity']);
        Route::post('/security-settings', [\App\Http\Controllers\Api\Admin\ProfileController::class, 'updateSecuritySettings']);
        Route::get('/current-ip', [\App\Http\Controllers\Api\Admin\ProfileController::class, 'getCurrentIp']);
    });
    
    // User Management
    Route::prefix('users')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\UserController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\Api\Admin\UserController::class, 'stats']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Admin\UserController::class, 'show']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\Admin\UserController::class, 'destroy']);
        Route::patch('/{id}/suspend', [\App\Http\Controllers\Api\Admin\UserController::class, 'suspend']);
    });

    // Products Management
    Route::get('/products', [\App\Http\Controllers\Api\Admin\ProductController::class, 'index']);
    Route::get('/products/metrics', [\App\Http\Controllers\Api\Admin\ProductController::class, 'metrics']);
    Route::get('/products/{id}', [\App\Http\Controllers\Api\Admin\ProductController::class, 'show']);
    Route::delete('/products/{id}', [\App\Http\Controllers\Api\Admin\ProductController::class, 'destroy']);
    Route::patch('/products/{id}/toggle-flag', [\App\Http\Controllers\Api\Admin\ProductController::class, 'toggleFlag']);

    
    // Support Management
    Route::prefix('support')->group(function () {
        Route::get('/tickets', [\App\Http\Controllers\Api\Admin\SupportController::class, 'index']);
        Route::get('/tickets/{id}', [\App\Http\Controllers\Api\Admin\SupportController::class, 'show']);
        Route::post('/tickets/{id}/reply', [\App\Http\Controllers\Api\Admin\SupportController::class, 'reply']);
        Route::patch('/tickets/{id}/status', [\App\Http\Controllers\Api\Admin\SupportController::class, 'updateStatus']);
    });

    // FAQ Management
    Route::prefix('faqs')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\FaqController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\Admin\FaqController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Admin\FaqController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\Admin\FaqController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\Admin\FaqController::class, 'destroy']);
    });

    // Announcements (broadcast to all users)
    Route::post('/announcements', [\App\Http\Controllers\Api\Admin\AnnouncementController::class, 'broadcast']);

});
