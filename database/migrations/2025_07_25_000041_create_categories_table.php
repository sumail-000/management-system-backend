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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Ensure unique category names per user
            $table->unique(['name', 'user_id']);
            
            // Index for faster queries
            $table->index('user_id');
        });
        
        // Add foreign key constraint to products table
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign key constraint from products table first
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });
        
        Schema::dropIfExists('categories');
    }
};
