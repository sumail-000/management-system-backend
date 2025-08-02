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
        Schema::table('user_permissions', function (Blueprint $table) {
            // Rename premium_plan_features to pro_plan_features to match documented plan structure
            $table->renameColumn('premium_plan_features', 'pro_plan_features');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_permissions', function (Blueprint $table) {
            // Revert the column name back to premium_plan_features
            $table->renameColumn('pro_plan_features', 'premium_plan_features');
        });
    }
};