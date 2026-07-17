<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('priority', 32)->nullable();
            $table->unsignedInteger('first_response_minutes')->nullable();
            $table->unsignedInteger('resolution_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'code']);
        });

        Schema::create('ticket_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 64)->unique();
            $table->string('type', 32); // customer|internal|whatsapp
            $table->string('channel', 32)->default('internal');
            $table->string('status', 32)->default('open');
            $table->string('priority', 32)->default('normal');
            $table->string('category')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('breached_at')->nullable();
            $table->boolean('sla_paused')->default(false);
            $table->timestamp('sla_paused_at')->nullable();
            $table->unsignedInteger('sla_paused_seconds')->default(0);
            $table->foreignId('major_incident_id')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->boolean('customer_confirmed')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'assignee_id']);
            $table->index(['customer_id', 'status']);
            $table->index(['conversation_id']);
            $table->index(['resolution_due_at', 'status']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('major_incident_id')->references('id')->on('tickets')->nullOnDelete();
        });

        Schema::create('ticket_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('ticket_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['ticket_id', 'user_id']);
        });

        Schema::create('ticket_intake_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inbound_message_id')->nullable();
            $table->string('meta_message_id')->nullable()->unique();
            $table->foreignId('conversation_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('pending'); // pending|accepted|dismissed|auto_created
            $table->string('suggested_type', 32)->default('whatsapp');
            $table->string('suggested_category')->nullable();
            $table->string('suggested_priority', 32)->default('normal');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['inbound_message_id']);
            $table->index(['status', 'branch_id']);
        });

        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('rule_code', 64);
            $table->string('level', 32)->default('l1');
            $table->string('status', 32)->default('open');
            $table->text('reason')->nullable();
            $table->foreignId('notified_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'status']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalations');
        Schema::dropIfExists('ticket_intake_suggestions');
        Schema::dropIfExists('ticket_watchers');
        Schema::dropIfExists('ticket_status_transitions');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_sequences');
        Schema::dropIfExists('sla_policies');
    }
};
