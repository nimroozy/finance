<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // customers|invoices|full|token_refresh|...
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('progress')->nullable();
            $table->json('stats')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('parent_job_id')->nullable()->constrained('zoho_sync_jobs')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_sync_jobs');
    }
};
