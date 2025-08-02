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
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->decimal('serving_size', 8, 2)->nullable();
            $table->string('serving_unit')->nullable();
            $table->decimal('servings_per_container', 8, 2)->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('status')->default('draft'); // 'draft' or 'published'
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