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
        // Table for diet and health labels
        Schema::create('product_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label_type'); // 'diet', 'health', 'caution'
            $table->string('label_value');
            $table->timestamps();
            
            // Prevent duplicate labels for the same product
            $table->unique(['product_id', 'label_type', 'label_value']);
            $table->index(['product_id', 'label_type']);
        });
        
        // Table for meal types and dish types
        Schema::create('product_meal_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type_category'); // 'meal', 'dish'
            $table->string('type_value'); // 'breakfast', 'main-course', etc.
            $table->timestamps();
            
            // Prevent duplicate types for the same product
            $table->unique(['product_id', 'type_category', 'type_value']);
            $table->index(['product_id', 'type_category']);
        });
        
        // Table for recipe tags (separate from product tags)
        Schema::create('product_recipe_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('tag_value');
            $table->string('tag_source')->default('recipe'); // 'recipe', 'user', 'auto'
            $table->timestamps();
            
            // Prevent duplicate tags for the same product
            $table->unique(['product_id', 'tag_value']);
            $table->index(['product_id', 'tag_source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_recipe_tags');
        Schema::dropIfExists('product_meal_types');
        Schema::dropIfExists('product_labels');
    }
};