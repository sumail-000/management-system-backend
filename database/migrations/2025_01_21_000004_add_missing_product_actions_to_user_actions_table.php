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
        Schema::table('user_actions', function (Blueprint $table) {
            // Add missing specific product management actions
            $table->boolean('product_view_trashed')->default(true)->after('product_bulk_operations');
            $table->boolean('product_categories_management')->default(true)->after('product_view_trashed');
            $table->boolean('product_tags_management')->default(true)->after('product_categories_management');
            
            // Rename existing column to be more specific
            // Note: We already have product_public_access and product_image_management
            // We already have product_categories_tags but let's make it more specific by splitting
        });
        
        // Update the combined categories_tags column to be more specific
        Schema::table('user_actions', function (Blueprint $table) {
            $table->dropColumn('product_categories_tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_actions', function (Blueprint $table) {
            // Remove the added columns
            $table->dropColumn([
                'product_view_trashed',
                'product_categories_management',
                'product_tags_management'
            ]);
            
            // Restore the original combined column
            $table->boolean('product_categories_tags')->default(true);
        });
    }
};