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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('cancellation_status', ['none', 'pending', 'confirmed', 'processed'])->default('none')->after('cancelled_at');
            $table->timestamp('cancellation_requested_at')->nullable()->after('cancellation_status');
            $table->timestamp('cancellation_effective_at')->nullable()->after('cancellation_requested_at');
            $table->text('cancellation_reason')->nullable()->after('cancellation_effective_at');
            $table->boolean('cancellation_confirmed')->default(false)->after('cancellation_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'cancellation_status',
                'cancellation_requested_at',
                'cancellation_effective_at',
                'cancellation_reason',
                'cancellation_confirmed'
            ]);
        });
    }
};
