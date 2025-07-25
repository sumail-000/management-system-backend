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
        Schema::table('settings', function (Blueprint $table) {
            // Drop old columns that are being renamed/restructured
            $table->dropColumn([
                'language_preference',
                'default_unit_system',
                'default_label_format'
            ]);
        });
        
        Schema::table('settings', function (Blueprint $table) {
            // Add new columns to match the Setting model
            $table->string('theme')->default('light')->after('user_id'); // light, dark
            $table->string('language')->default('english')->after('theme'); // english, arabic
            $table->string('timezone')->default('UTC')->after('language');
            $table->boolean('email_notifications')->default(true)->after('timezone');
            $table->boolean('push_notifications')->default(true)->after('email_notifications');
            $table->string('default_serving_unit')->default('grams')->after('push_notifications'); // grams, ounces, pounds, kilograms
            $table->json('label_template_preferences')->nullable()->after('default_serving_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Remove new columns
            $table->dropColumn([
                'theme',
                'language',
                'timezone',
                'email_notifications',
                'push_notifications',
                'default_serving_unit',
                'label_template_preferences'
            ]);
        });
        
        Schema::table('settings', function (Blueprint $table) {
            // Restore old columns
            $table->string('language_preference')->default('english');
            $table->string('default_unit_system')->default('metric');
            $table->string('default_label_format')->default('vertical');
        });
    }
};