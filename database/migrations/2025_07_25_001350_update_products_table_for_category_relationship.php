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
            // Drop the old string category column
            $table->dropColumn('category');
            
            // Add the new category_id foreign key column
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null')->after('description');
            
            // Add index for better performance
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop the foreign key and index
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
            
            // Restore the old string category column
            $table->string('category')->nullable()->after('description');
        });
    }
};
