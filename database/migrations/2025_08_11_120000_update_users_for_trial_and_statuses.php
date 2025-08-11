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
        // Add trial columns if missing
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'trial_started_at')) {
                $table->timestamp('trial_started_at')->nullable()->after('stripe_subscription_id');
            }
            if (!Schema::hasColumn('users', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
            }
        });

        // Extend payment_status enum to include trial and expired (MySQL)
        $connection = config('database.default');
        $driver = config("database.connections.$connection.driver");
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `payment_status` ENUM('pending','active','paid','failed','cancelled','trial','expired') DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            // For PostgreSQL or others, fallback to a VARCHAR to avoid enum alteration complexity
            DB::statement("ALTER TABLE users ALTER COLUMN payment_status TYPE VARCHAR(20)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert trial columns
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'trial_ends_at')) {
                $table->dropColumn('trial_ends_at');
            }
            if (Schema::hasColumn('users', 'trial_started_at')) {
                $table->dropColumn('trial_started_at');
            }
        });

        $connection = config('database.default');
        $driver = config("database.connections.$connection.driver");
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `payment_status` ENUM('pending','active','paid','failed','cancelled') DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            // No-op or reduce to VARCHAR(20) remains the same
            DB::statement("ALTER TABLE users ALTER COLUMN payment_status TYPE VARCHAR(20)");
        }
    }
};
