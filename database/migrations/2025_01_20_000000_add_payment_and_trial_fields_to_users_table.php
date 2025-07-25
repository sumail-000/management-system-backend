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
            $table->enum('payment_status', ['pending', 'paid', 'trial', 'expired'])->default('trial')->after('membership_plan_id');
            $table->timestamp('trial_started_at')->nullable()->after('payment_status');
            $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
            $table->timestamp('subscription_started_at')->nullable()->after('trial_ends_at');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_started_at');
            $table->string('stripe_customer_id')->nullable()->after('subscription_ends_at');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'trial_started_at',
                'trial_ends_at',
                'subscription_started_at',
                'subscription_ends_at',
                'stripe_customer_id',
                'stripe_subscription_id'
            ]);
        });
    }
};