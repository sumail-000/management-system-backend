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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'card', 'bank_account', etc.
            $table->string('provider'); // 'stripe', 'paypal', etc.
            $table->string('provider_payment_method_id'); // External payment method ID
            $table->string('stripe_payment_method_id')->nullable(); // Stripe-specific payment method ID
            $table->string('brand')->nullable(); // 'visa', 'mastercard', etc.
            $table->string('last_four', 4); // Last 4 digits only
            $table->integer('expiry_month')->nullable(); // For cards (as integer)
            $table->integer('expiry_year')->nullable(); // For cards (as integer)
            $table->string('cardholder_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_default']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
