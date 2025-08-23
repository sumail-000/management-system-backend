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
        // Stores scheduled report definitions
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('report_key'); // e.g., user_activity, revenue_summary, etc.
            $table->string('cadence'); // daily, weekly, monthly, quarterly
            $table->string('format')->default('csv'); // csv, json, pdf
            $table->json('params')->nullable(); // optional parameters (e.g., relative ranges)
            $table->json('recipients')->nullable(); // array of emails
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
            $table->index(['report_key', 'cadence', 'is_active']);
            $table->index('next_run_at');
        });

        // Stores generated report files/metadata
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_key');
            $table->string('format'); // csv, json, pdf
            $table->json('params'); // include from/to or month range used
            $table->string('status')->default('ready'); // ready, failed
            $table->string('file_path')->nullable(); // storage path if persisted
            $table->unsignedBigInteger('admin_id')->nullable(); // who triggered
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
            $table->index(['report_key', 'format', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
        Schema::dropIfExists('report_schedules');
    }
};
