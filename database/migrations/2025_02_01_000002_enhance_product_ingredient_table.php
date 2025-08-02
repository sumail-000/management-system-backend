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
        Schema::table('product_ingredient', function (Blueprint $table) {
            // Enhanced ingredient data from Edamam API
            $table->string('text')->nullable()->after('order'); // Original ingredient text
            $table->decimal('quantity', 10, 3)->nullable()->after('text'); // Parsed quantity
            $table->string('measure')->nullable()->after('quantity'); // cup, tablespoon, etc.
            $table->decimal('weight', 10, 2)->nullable()->after('measure'); // Weight in grams
            $table->string('food_category')->nullable()->after('weight'); // Food category
            $table->string('food_id')->nullable()->after('food_category'); // Edamam food ID
            $table->text('image_url')->nullable()->after('food_id'); // Ingredient image
            $table->boolean('is_main_ingredient')->default(false)->after('image_url');
            
            // Additional metadata
            $table->json('nutrition_data')->nullable()->after('is_main_ingredient'); // Individual ingredient nutrition
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_ingredient', function (Blueprint $table) {
            $table->dropColumn([
                'text',
                'quantity',
                'measure',
                'weight',
                'food_category',
                'food_id',
                'image_url',
                'is_main_ingredient',
                'nutrition_data'
            ]);
        });
    }
};