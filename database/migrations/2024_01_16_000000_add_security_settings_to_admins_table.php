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
        Schema::table('admins', function (Blueprint $table) {
            $table->boolean('ip_restriction_enabled')->default(false)->after('is_active');
            $table->json('allowed_ips')->nullable()->after('ip_restriction_enabled');
            $table->boolean('two_factor_enabled')->default(false)->after('allowed_ips');
            $table->string('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->boolean('login_notifications_enabled')->default(false)->after('two_factor_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn([
                'ip_restriction_enabled',
                'allowed_ips',
                'two_factor_enabled',
                'two_factor_secret',
                'login_notifications_enabled'
            ]);
        });
    }
};