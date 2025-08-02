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
        Schema::table('products', function (Blueprint $table) {
            // Recipe basic information
            $table->string('recipe_uri')->nullable()->after('ingredient_notes');
            $table->string('recipe_source')->nullable()->after('recipe_uri');
            $table->text('source_url')->nullable()->after('recipe_source');
            
            // Timing information
            $table->integer('prep_time')->nullable()->after('recipe_url'); // in minutes
            $table->integer('cook_time')->nullable()->after('prep_time'); // in minutes
            $table->integer('total_time')->nullable()->after('cook_time'); // in minutes
            
            // Classification
            $table->string('skill_level')->nullable()->after('total_time'); // beginner, intermediate, advanced
            $table->string('time_category')->nullable()->after('skill_level'); // quick, moderate, long
            $table->string('cuisine_type')->nullable()->after('time_category');
            $table->string('difficulty')->nullable()->after('cuisine_type'); // easy, medium, hard
            
            // Environmental data
            $table->decimal('total_co2_emissions', 10, 2)->nullable()->after('difficulty');
            $table->string('co2_emissions_class')->nullable()->after('total_co2_emissions');
            
            // Recipe yield and serving info
            $table->integer('recipe_yield')->nullable()->after('co2_emissions_class');
            $table->decimal('total_weight', 10, 2)->nullable()->after('recipe_yield');
            $table->decimal('weight_per_serving', 10, 2)->nullable()->after('total_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'recipe_uri',
                'recipe_source',
                'source_url',
                'prep_time',
                'cook_time',
                'total_time',
                'skill_level',
                'time_category',
                'cuisine_type',
                'difficulty',
                'total_co2_emissions',
                'co2_emissions_class',
                'recipe_yield',
                'total_weight',
                'weight_per_serving'
            ]);
        });
    }
};