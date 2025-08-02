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
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Permission Controls - all default to true
            $table->boolean('authentication_system')->default(true);
            $table->boolean('usage_tracking_system')->default(true);
            $table->boolean('admin_role')->default(true);
            
            // System functionality permissions
            $table->boolean('product_crud_operations')->default(true);
            $table->boolean('product_image_management_permission')->default(true);
            $table->boolean('product_duplication_system')->default(true);
            $table->boolean('product_pin_management_permission')->default(true);
            $table->boolean('product_trash_management_permission')->default(true);
            $table->boolean('product_bulk_operations_permission')->default(true);
            $table->boolean('product_public_access_permission')->default(true);
            $table->boolean('product_categories_tags_permission')->default(true);
            
            // Billing functionality permissions
            $table->boolean('billing_information_management_permission')->default(true);
            $table->boolean('payment_method_management_permission')->default(true);
            $table->boolean('subscription_management_permission')->default(true);
            $table->boolean('billing_history_invoices_permission')->default(true);
            $table->boolean('auto_renewal_system_permission')->default(true);
            $table->boolean('payment_status_tracking_permission')->default(true);
            $table->boolean('stripe_integration_permission')->default(true);
            $table->boolean('billing_security_permissions')->default(true);
            
            // Role-based permissions
            $table->boolean('user_role_access')->default(true);
            $table->boolean('admin_role_access')->default(false); // Admin access defaults to false
            
            // Plan-based permissions
            $table->boolean('basic_plan_features')->default(true);
            $table->boolean('premium_plan_features')->default(false);
            $table->boolean('enterprise_plan_features')->default(false);
            
            // API and system permissions
            $table->boolean('api_access')->default(true);
            $table->boolean('frontend_access')->default(true);
            $table->boolean('dashboard_permission')->default(true);
            
            $table->timestamps();
            
            // Ensure one record per user
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};