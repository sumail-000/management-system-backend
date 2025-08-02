<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_permissions', function (Blueprint $table) {
            // Middleware-specific permissions
            $table->boolean('auth_sanctum_middleware')->default(true)->after('authentication_system');
            $table->boolean('token_refresh_middleware')->default(true)->after('auth_sanctum_middleware');
            $table->boolean('dashboard_access_middleware')->default(true)->after('token_refresh_middleware');
            
            // Audit trail and logging permissions
            $table->boolean('audit_trail_access')->default(true)->after('billing_security_permissions');
            $table->boolean('user_action_tracking')->default(true)->after('audit_trail_access');
            $table->boolean('authentication_event_logging')->default(true)->after('user_action_tracking');
            $table->boolean('payment_subscription_logging')->default(true)->after('authentication_event_logging');
            
            // Frontend security permissions
            $table->boolean('protected_routes_access')->default(true)->after('frontend_access');
            $table->boolean('conditional_rendering_permission')->default(true)->after('protected_routes_access');
            $table->boolean('state_management_permission')->default(true)->after('conditional_rendering_permission');
            
            // Data protection and ownership validation permissions
            $table->boolean('data_protection_permission')->default(true)->after('state_management_permission');
            $table->boolean('product_ownership_validation')->default(true)->after('data_protection_permission');
            $table->boolean('invoice_ownership_validation')->default(true)->after('product_ownership_validation');
            $table->boolean('user_ownership_validation')->default(true)->after('invoice_ownership_validation');
            
            // Access control permissions
            $table->boolean('token_based_authentication')->default(true)->after('user_ownership_validation');
            $table->boolean('middleware_validation_permission')->default(true)->after('token_based_authentication');
            $table->boolean('payment_status_verification')->default(true)->after('middleware_validation_permission');
            $table->boolean('trial_expiration_monitoring')->default(true)->after('payment_status_verification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_permissions', function (Blueprint $table) {
            $table->dropColumn([
                'auth_sanctum_middleware',
                'token_refresh_middleware',
                'dashboard_access_middleware',
                'audit_trail_access',
                'user_action_tracking',
                'authentication_event_logging',
                'payment_subscription_logging',
                'protected_routes_access',
                'conditional_rendering_permission',
                'state_management_permission',
                'data_protection_permission',
                'product_ownership_validation',
                'invoice_ownership_validation',
                'user_ownership_validation',
                'token_based_authentication',
                'middleware_validation_permission',
                'payment_status_verification',
                'trial_expiration_monitoring'
            ]);
        });
    }
};