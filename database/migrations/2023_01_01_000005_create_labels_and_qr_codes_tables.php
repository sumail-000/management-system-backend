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
        // Create QR codes table first since labels will reference it
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('url_slug')->unique();
            $table->string('image_path')->nullable();
            $table->integer('scan_count')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
        });

        // Create labels table
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('format')->default('vertical'); // 'vertical' or 'horizontal'
            $table->string('language')->default('bilingual'); // 'english', 'arabic', or 'bilingual'
            $table->string('unit_system')->default('metric'); // 'metric' or 'imperial'
            $table->foreignId('qr_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('logo_path')->nullable(); // For storing logo/branding
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labels');
        Schema::dropIfExists('qr_codes');
    }
};