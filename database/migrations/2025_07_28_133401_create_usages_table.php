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
        Schema::create('usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('month'); // Format: YYYY-MM
            $table->integer('products')->default(0);
            $table->integer('nutrition_analyses')->default(0);
            $table->integer('qr_codes')->default(0);
            $table->integer('labels')->default(0);
            $table->timestamps();
            
            // Ensure one record per user per month
            $table->unique(['user_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usages');
    }
};




