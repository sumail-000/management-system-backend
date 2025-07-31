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
        Schema::table('nutritional_data', function (Blueprint $table) {
            // Add fields for complete nutrition analysis storage
            $table->json('total_nutrients')->nullable()->after('nutrition_summary'); // Complete nutrients from API
            $table->json('total_daily')->nullable()->after('total_nutrients'); // Daily values from API
            $table->json('total_nutrients_kcal')->nullable()->after('total_daily'); // Calorie breakdown
            
            // Environmental and source data
            $table->decimal('total_co2_emissions', 10, 2)->nullable()->after('total_nutrients_kcal');
            $table->string('co2_emissions_class')->nullable()->after('total_co2_emissions');
            $table->decimal('total_weight', 10, 2)->nullable()->after('co2_emissions_class');
            
            // Analysis metadata
            $table->string('analysis_source')->default('edamam')->after('total_weight');
            $table->string('analysis_version')->nullable()->after('analysis_source');
            $table->string('request_id')->nullable()->after('analysis_version'); // For tracking API requests
            $table->boolean('is_cached')->default(false)->after('request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nutritional_data', function (Blueprint $table) {
            $table->dropColumn([
                'total_nutrients',
                'total_daily',
                'total_nutrients_kcal',
                'total_co2_emissions',
                'co2_emissions_class',
                'total_weight',
                'analysis_source',
                'analysis_version',
                'request_id',
                'is_cached'
            ]);
        });
    }
};