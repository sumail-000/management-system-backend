<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique(); // TK-YYYY-XXX
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50); // general, billing, technical, account, api
            $table->string('priority', 20); // low, medium, high, urgent
            $table->string('subject', 255);
            $table->string('status', 20)->default('open'); // open, pending, resolved, closed
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // end-user author (nullable for admin message)
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete(); // admin author (nullable for user message)
            $table->boolean('is_admin')->default(false);
            $table->text('message');
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->string('category', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
