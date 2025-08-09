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
        Schema::table('products', function (Blueprint $table) {
            // Add JSON column for storing ingredient data with nutrition information
            $table->json('ingredients_data')->nullable()->after('ingredient_notes');
            
            // Add fields for progressive recipe creation
            $table->json('nutrition_data')->nullable()->after('ingredients_data');
            $table->json('serving_configuration')->nullable()->after('nutrition_data');
            $table->json('ingredient_statements')->nullable()->after('serving_configuration');
            $table->decimal('total_weight', 10, 2)->nullable()->after('ingredient_statements');
            $table->integer('servings_per_container')->default(1)->after('total_weight');
            $table->decimal('serving_size_grams', 8, 2)->nullable()->after('servings_per_container');
            
            // Add recipe creation progress tracking
            $table->enum('creation_step', [
                'name_created',
                'details_configured',
                'ingredients_added',
                'nutrition_analyzed',
                'serving_configured',
                'completed'
            ])->default('name_created')->after('status');
            
            // Add timestamps for progressive creation tracking
            $table->timestamp('ingredients_updated_at')->nullable()->after('serving_size_grams');
            $table->timestamp('nutrition_updated_at')->nullable()->after('ingredients_updated_at');
            $table->timestamp('serving_updated_at')->nullable()->after('nutrition_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'ingredients_data',
                'nutrition_data',
                'serving_configuration',
                'ingredient_statements',
                'total_weight',
                'servings_per_container',
                'serving_size_grams',
                'creation_step',
                'ingredients_updated_at',
                'nutrition_updated_at',
                'serving_updated_at'
            ]);
        });
    }
};