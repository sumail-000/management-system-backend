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
            // Recipe rating and nutrition score
            $table->decimal('datametrics_rating', 3, 2)->nullable()->after('recipe_tags'); // Rating out of 5.00
            $table->decimal('nutrition_score', 5, 2)->nullable()->after('datametrics_rating'); // Nutrition/diversity score out of 100.00
            
            // Individual macronutrient fields per serving
            $table->decimal('protein_per_serving', 10, 2)->nullable()->after('nutrition_score'); // Protein in grams
            $table->decimal('carbs_per_serving', 10, 2)->nullable()->after('protein_per_serving'); // Carbohydrates in grams
            $table->decimal('fat_per_serving', 10, 2)->nullable()->after('carbs_per_serving'); // Fat in grams
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'datametrics_rating',
                'nutrition_score',
                'protein_per_serving',
                'carbs_per_serving',
                'fat_per_serving'
            ]);
        });
    }
};