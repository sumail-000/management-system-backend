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
        // Create settings table
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('theme')->default('light'); // light, dark
            $table->string('language')->default('english'); // english, arabic
            $table->string('timezone')->default('UTC');
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(true);
            $table->string('default_serving_unit')->default('grams'); // grams, ounces, pounds, kilograms
            $table->json('label_template_preferences')->nullable();
            $table->timestamps();
        });

        // Create API logs table
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('endpoint');
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('status_code')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('settings');
    }
};