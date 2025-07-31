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
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->string('unique_code')->nullable()->unique()->after('url_slug');
            $table->boolean('is_premium')->default(false)->after('unique_code');
            $table->json('analytics_data')->nullable()->after('is_premium');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropColumn(['unique_code', 'is_premium', 'analytics_data']);
        });
    }
};
