<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('visibility', 32)->default('internal'); // internal|customer
            $table->text('body');
            $table->unsignedInteger('minutes_spent')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
            $table->index(['task_id', 'created_at']);
        });

        Schema::create('work_log_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amended_by')->constrained('users')->cascadeOnDelete();
            $table->text('previous_body');
            $table->text('new_body');
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('operational_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('virus_scan_status', 32)->default('pending');
            $table->string('thumbnail_path')->nullable();
            $table->string('thumbnail_status', 32)->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_attachments');
        Schema::dropIfExists('work_log_amendments');
        Schema::dropIfExists('work_logs');
    }
};
