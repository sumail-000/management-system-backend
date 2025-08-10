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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('membership_plan_id')->nullable();
            $table->string('company')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamp('subscription_started_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->enum('payment_status', ['pending', 'active', 'paid', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('cancelled_at')->nullable();
            $table->enum('cancellation_status', ['none', 'pending', 'confirmed', 'processed'])->default('none');
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->timestamp('cancellation_effective_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('cancellation_confirmed')->default(false);
            $table->timestamp('deletion_scheduled_at')->nullable();
            $table->text('deletion_reason')->nullable();
            $table->timestamp('last_usage_warning_sent_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            // Note: Foreign key constraint for membership_plan_id will be added after membership_plans table is created
        });

        // Create admins table
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->enum('role', ['super_admin', 'admin', 'moderator'])->default('admin');
            $table->json('permissions')->nullable(); // Store specific permissions
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->text('notes')->nullable(); // Admin notes
            $table->unsignedBigInteger('created_by')->nullable(); // Which admin created this admin
            $table->rememberToken();
            $table->timestamps();
            
            // Foreign key for created_by
            $table->foreign('created_by')->references('id')->on('admins')->onDelete('set null');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
