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
        Schema::create('admin_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->string('action'); // e.g., 'login', 'profile_update', 'password_change', 'security_setting_change'
            $table->string('description'); // Human readable description
            $table->string('type'); // e.g., 'login', 'profile', 'security', 'system'
            $table->json('metadata')->nullable(); // Additional data like IP, user agent, changes made, etc.
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['admin_id', 'created_at']);
            $table->index('type');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_activities');
    }
};