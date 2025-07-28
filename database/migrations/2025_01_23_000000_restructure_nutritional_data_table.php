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
        // Drop the existing table and recreate with optimized structure
        Schema::dropIfExists('nutritional_data');
        
        Schema::create('nutritional_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            // Core nutrition data - store as JSON to match frontend structure
            $table->json('basic_nutrition'); // { total_calories, servings, weight_per_serving }
            $table->json('macronutrients'); // { protein, carbohydrates, fat, fiber }
            $table->json('micronutrients'); // Complete micronutrients object
            $table->json('daily_values'); // Daily value percentages
            
            // Health and compliance data
            $table->json('health_labels')->nullable(); // Array of health labels
            $table->json('diet_labels')->nullable(); // Array of diet labels
            $table->json('allergens')->nullable(); // Array of allergen data
            $table->json('warnings')->nullable(); // Array of warning objects
            $table->json('high_nutrients')->nullable(); // Array of high nutrient data
            $table->json('nutrition_summary')->nullable(); // Nutrition summary object
            
            // Analysis metadata
            $table->json('analysis_metadata'); // { analyzed_at, ingredient_query, product_name }
            
            // Quick access fields for queries (extracted from JSON)
            $table->decimal('total_calories', 8, 2)->nullable();
            $table->decimal('servings', 8, 2)->nullable();
            $table->decimal('weight_per_serving', 8, 2)->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index('product_id');
            $table->index('user_id');
            $table->index('total_calories');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nutritional_data');
        
        // Recreate the old structure if needed
        Schema::create('nutritional_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('analysis_name')->nullable();
            $table->json('ingredients')->nullable();
            $table->json('nutrition_data')->nullable();
            $table->decimal('total_calories', 8, 2)->nullable();
            $table->decimal('servings', 8, 2)->nullable();
            $table->decimal('weight_per_serving', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->decimal('calories', 8, 2)->nullable();
            $table->decimal('total_fat', 8, 2)->nullable();
            $table->decimal('saturated_fat', 8, 2)->nullable();
            $table->decimal('trans_fat', 8, 2)->nullable();
            $table->decimal('cholesterol', 8, 2)->nullable();
            $table->decimal('sodium', 8, 2)->nullable();
            $table->decimal('total_carbohydrate', 8, 2)->nullable();
            $table->decimal('dietary_fiber', 8, 2)->nullable();
            $table->decimal('sugars', 8, 2)->nullable();
            $table->decimal('protein', 8, 2)->nullable();
            $table->decimal('vitamin_a', 8, 2)->nullable();
            $table->decimal('vitamin_c', 8, 2)->nullable();
            $table->decimal('calcium', 8, 2)->nullable();
            $table->decimal('iron', 8, 2)->nullable();
            $table->decimal('potassium', 8, 2)->nullable();
            $table->json('edamam_response')->nullable();
            $table->timestamps();
            $table->index('product_id');
            $table->index('user_id');
        });
    }
};