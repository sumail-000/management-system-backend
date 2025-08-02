<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['product_id']);
            
            // Modify the column to be nullable
            $table->unsignedBigInteger('product_id')->nullable()->change();
            
            // Re-add the foreign key constraint with nullable support
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['product_id']);
            
            // Make the column non-nullable again
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            
            // Re-add the foreign key constraint
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};