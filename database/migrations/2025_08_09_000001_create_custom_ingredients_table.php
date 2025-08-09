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
        Schema::create('custom_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // General Information
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->text('ingredient_list')->nullable();
            
            // Serving Information
            $table->decimal('serving_size', 10, 2)->default(100);
            $table->string('serving_unit', 20)->default('g');
            
            // Basic Nutrition Information (JSON for flexibility)
            $table->json('nutrition_data')->nullable();
            
            // Vitamins and Minerals (JSON for flexibility)
            $table->json('vitamins_minerals')->nullable();
            
            // Additional Nutrients (JSON for flexibility)
            $table->json('additional_nutrients')->nullable();
            
            // Allergen Information (JSON for flexibility)
            $table->json('allergens_data')->nullable();
            
            // Additional Notes
            $table->text('nutrition_notes')->nullable();
            
            // Status and Metadata
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('is_public')->default(false);
            $table->integer('usage_count')->default(0); // Track how many times it's used
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'name']);
            $table->index(['category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_ingredients');
    }
};