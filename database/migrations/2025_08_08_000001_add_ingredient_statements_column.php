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
            // Add the missing ingredient_statements column
            if (!Schema::hasColumn('products', 'ingredient_statements')) {
                $table->json('ingredient_statements')->nullable()->after('serving_configuration');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'ingredient_statements')) {
                $table->dropColumn('ingredient_statements');
            }
        });
    }
};