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
            // Add missing billing management actions based on documentation analysis
            $table->boolean('payment_status_check')->default(true)->after('payment_status_view');
            $table->boolean('cancellation_request')->default(true)->after('payment_status_check');
            $table->boolean('cancellation_confirm')->default(true)->after('cancellation_request');
            $table->boolean('cancellation_cancel')->default(true)->after('cancellation_confirm');
            $table->boolean('auto_renewal_update')->default(true)->after('cancellation_cancel');
            $table->boolean('subscription_details_view')->default(true)->after('auto_renewal_update');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_actions', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status_check',
                'cancellation_request',
                'cancellation_confirm',
                'cancellation_cancel',
                'auto_renewal_update',
                'subscription_details_view'
            ]);
        });
    }
};