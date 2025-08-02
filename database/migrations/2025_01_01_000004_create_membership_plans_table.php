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
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->enum('name', ['Basic', 'Pro', 'Enterprise']);
            $table->decimal('price', 8, 2);
            $table->string('stripe_price_id')->nullable();
            $table->text('description');
            $table->json('features');
            $table->integer('product_limit')->default(0); // 0 means unlimited
            $table->integer('label_limit')->default(0); // 0 means unlimited
            $table->integer('qr_code_limit')->default(0); // 0 means unlimited
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
