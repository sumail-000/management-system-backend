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
        Schema::table('user_actions', function (Blueprint $table) {
            // Add missing user profile management actions
            $table->boolean('usage_statistics_view')->default(true)->after('user_profile_management');
            
            // Add missing membership plan management actions
            $table->boolean('plan_recommendations_get')->default(true)->after('membership_plan_management');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_actions', function (Blueprint $table) {
            $table->dropColumn([
                'usage_statistics_view',
                'plan_recommendations_get'
            ]);
        });
    }
};