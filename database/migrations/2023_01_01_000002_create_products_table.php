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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->decimal('serving_size', 8, 2)->nullable();
            $table->string('serving_unit')->nullable();
            $table->decimal('servings_per_container', 8, 2)->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('status')->default('draft'); // 'draft' or 'published'
            $table->text('ingredient_notes')->nullable();
            $table->text('image_url')->nullable();
            $table->string('image_path')->nullable();
            
            // Recipe basic information
            $table->string('recipe_uri')->nullable();
            $table->string('recipe_source')->nullable();
            $table->text('source_url')->nullable();
            
            // Timing information
            $table->integer('prep_time')->nullable(); // in minutes
            $table->integer('cook_time')->nullable(); // in minutes
            $table->integer('total_time')->nullable(); // in minutes
            
            // Classification
            $table->string('skill_level')->nullable(); // beginner, intermediate, advanced
            $table->string('time_category')->nullable(); // quick, moderate, long
            $table->string('cuisine_type')->nullable();
            $table->string('difficulty')->nullable(); // easy, medium, hard
            
            // Environmental data
            $table->decimal('total_co2_emissions', 10, 2)->nullable();
            $table->string('co2_emissions_class')->nullable();
            
            // Recipe yield and serving info
            $table->integer('recipe_yield')->nullable();
            $table->decimal('total_weight', 10, 2)->nullable();
            $table->decimal('weight_per_serving', 10, 2)->nullable();
            
            // Recipe calorie information
            $table->decimal('total_recipe_calories', 10, 2)->nullable();
            $table->decimal('calories_per_serving_recipe', 10, 2)->nullable();
            
            // Recipe metadata as JSON columns
            $table->json('diet_labels')->nullable();
            $table->json('health_labels')->nullable();
            $table->json('caution_labels')->nullable();
            $table->json('meal_types')->nullable();
            $table->json('dish_types')->nullable();
            $table->json('recipe_tags')->nullable();
            
            // Recipe rating and nutrition score
            $table->decimal('datametrics_rating', 3, 2)->nullable(); // Rating out of 5.00
            $table->decimal('nutrition_score', 5, 2)->nullable(); // Nutrition/diversity score out of 100.00
            
            // Individual macronutrient fields per serving
            $table->decimal('protein_per_serving', 10, 2)->nullable(); // Protein in grams
            $table->decimal('carbs_per_serving', 10, 2)->nullable(); // Carbohydrates in grams
            $table->decimal('fat_per_serving', 10, 2)->nullable(); // Fat in grams
            
            $table->timestamps();
            $table->softDeletes(); // For soft delete functionality
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};