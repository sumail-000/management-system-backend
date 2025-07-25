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
        Schema::create('nutritional_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
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
            $table->json('edamam_response')->nullable(); // Store raw API response
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nutritional_data');
    }
};