<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zoho_sync_job_id')->nullable()->constrained('zoho_sync_jobs')->nullOnDelete();
            $table->string('request_type');
            $table->string('endpoint_name');
            $table->string('method', 10);
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('local_entity_id')->nullable();
            $table->string('zoho_entity_id')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('zoho_code')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['endpoint_name', 'created_at']);
            $table->index('zoho_sync_job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_api_logs');
    }
};
