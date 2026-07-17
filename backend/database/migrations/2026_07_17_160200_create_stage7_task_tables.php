<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 64)->unique();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('installation_id')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('offered_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('open');
            $table->string('priority', 32)->default('normal');
            $table->string('work_type', 32)->default('office'); // office|field
            $table->json('checklist')->nullable();
            $table->text('block_reason')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('traveled_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['assignee_id', 'status']);
            $table->index(['ticket_id']);
            $table->index(['installation_id']);
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['task_id', 'depends_on_task_id']);
        });

        Schema::create('task_assignment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignment_events');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_sequences');
    }
};
