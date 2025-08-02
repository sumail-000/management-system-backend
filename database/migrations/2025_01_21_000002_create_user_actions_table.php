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
        Schema::create('user_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // User Actions - all default to true
            $table->boolean('dashboard_access')->default(true);
            $table->boolean('product_management')->default(true);
            $table->boolean('billing_management')->default(true);
            $table->boolean('user_profile_management')->default(true);
            $table->boolean('membership_plan_management')->default(true);
            
            // Product specific actions
            $table->boolean('product_create')->default(true);
            $table->boolean('product_read')->default(true);
            $table->boolean('product_update')->default(true);
            $table->boolean('product_delete')->default(true);
            $table->boolean('product_duplicate')->default(true);
            $table->boolean('product_pin')->default(true);
            $table->boolean('product_restore')->default(true);
            $table->boolean('product_force_delete')->default(true);
            $table->boolean('product_bulk_operations')->default(true);
            $table->boolean('product_public_access')->default(true);
            $table->boolean('product_image_management')->default(true);
            $table->boolean('product_categories_tags')->default(true);
            
            // Billing specific actions
            $table->boolean('billing_information_management')->default(true);
            $table->boolean('payment_method_management')->default(true);
            $table->boolean('subscription_management')->default(true);
            $table->boolean('billing_history_access')->default(true);
            $table->boolean('invoice_download')->default(true);
            $table->boolean('auto_renewal_control')->default(true);
            $table->boolean('payment_status_view')->default(true);
            
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
        Schema::dropIfExists('user_actions');
    }
};