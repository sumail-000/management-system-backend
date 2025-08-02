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
        Schema::create('qr_code_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); // 'created', 'deleted'
            $table->foreignId('qr_code_id')->nullable()->constrained('qr_codes')->nullOnDelete();
            $table->string('qr_code_type')->nullable(); // 'product', 'url', 'custom'
            $table->json('metadata')->nullable(); // Additional data like product_id, url, etc.
            $table->timestamp('event_date');
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['user_id', 'event_type']);
            $table->index(['user_id', 'event_date']);
            $table->index(['event_type', 'event_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_code_analytics');
    }
};