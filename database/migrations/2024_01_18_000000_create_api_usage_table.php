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
        Schema::create('api_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('api_provider', 50)->default('edamam'); // edamam, openai, etc.
            $table->string('api_service', 100); // nutrition, food_database, recipe_search, etc.
            $table->string('endpoint', 255); // specific API endpoint called
            $table->string('method', 10)->default('GET'); // HTTP method
            $table->json('request_data')->nullable(); // request parameters (sanitized)
            $table->integer('response_status')->nullable(); // HTTP response status
            $table->json('response_metadata')->nullable(); // response metadata (size, count, etc.)
            $table->decimal('response_time', 8, 3)->nullable(); // response time in seconds
            $table->integer('request_size')->nullable(); // request size in bytes
            $table->integer('response_size')->nullable(); // response size in bytes
            $table->string('ip_address', 45)->nullable(); // client IP address
            $table->text('user_agent')->nullable(); // client user agent
            $table->boolean('success')->default(true); // whether the API call was successful
            $table->text('error_message')->nullable(); // error message if failed
            $table->decimal('cost', 10, 6)->nullable(); // API call cost if applicable
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['api_provider', 'api_service', 'created_at']);
            $table->index(['created_at', 'success']);
            $table->index(['user_id', 'api_provider', 'created_at']);
            
            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_usage');
    }
};