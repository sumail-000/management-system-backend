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
        Schema::create('billing_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('membership_plan_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('payment_method_id')->nullable()->constrained()->onDelete('set null');
            $table->string('invoice_number')->unique();
            $table->string('transaction_id')->nullable(); // External payment processor transaction ID
            $table->enum('type', ['subscription', 'upgrade', 'downgrade', 'refund', 'credit']);
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'cancelled']);
            $table->timestamp('billing_date');
            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable(); // Additional data like tax info, discounts, etc.
            $table->string('invoice_url')->nullable(); // URL to downloadable invoice
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'billing_date']);
            $table->index('invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_history');
    }
};
