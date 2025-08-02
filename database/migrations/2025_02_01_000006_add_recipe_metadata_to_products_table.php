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
            // Recipe metadata as JSON columns
            $table->json('diet_labels')->nullable()->after('calories_per_serving_recipe');
            $table->json('health_labels')->nullable()->after('diet_labels');
            $table->json('caution_labels')->nullable()->after('health_labels');
            $table->json('meal_types')->nullable()->after('caution_labels');
            $table->json('dish_types')->nullable()->after('meal_types');
            $table->json('recipe_tags')->nullable()->after('dish_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'diet_labels',
                'health_labels',
                'caution_labels',
                'meal_types',
                'dish_types',
                'recipe_tags'
            ]);
        });
    }
};