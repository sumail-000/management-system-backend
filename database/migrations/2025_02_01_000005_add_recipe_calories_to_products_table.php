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
            // Add recipe calorie information
            $table->decimal('total_recipe_calories', 10, 2)->nullable()->after('weight_per_serving');
            $table->decimal('calories_per_serving_recipe', 10, 2)->nullable()->after('total_recipe_calories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'total_recipe_calories',
                'calories_per_serving_recipe'
            ]);
        });
    }
};