<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name_en');
            $table->string('name_fa')->nullable();
            $table->string('default_department_code', 64)->nullable();
            $table->string('default_priority', 32)->default('medium');
            $table->foreignId('sla_policy_id')->nullable();
            $table->boolean('requires_customer')->default(true);
            $table->boolean('requires_site')->default(false);
            $table->boolean('expects_field_visit')->default(false);
            $table->boolean('requires_finance_review')->default(false);
            $table->boolean('requires_radius_review')->default(false);
            $table->json('required_fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticket_type_code', 64)->nullable();
            $table->string('priority', 32)->nullable();
            $table->string('customer_tier', 32)->nullable();
            $table->json('business_hours')->nullable();
            $table->unsignedInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->json('escalation_intervals')->nullable();
            $table->json('pause_statuses')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['ticket_type_code', 'priority', 'is_active']);
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->foreign('sla_policy_id')->references('id')->on('sla_policies')->nullOnDelete();
        });

        Schema::create('ticket_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('task_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('installation_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_number', 64)->nullable();
            $table->string('source', 32)->default('internal');
            $table->string('type_code', 64);
            $table->string('category', 64)->nullable();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('priority', 32)->default('medium');
            $table->string('severity', 32)->nullable();
            $table->string('impact', 32)->nullable();
            $table->string('urgency', 32)->nullable();
            $table->string('status', 32)->default('open');
            $table->foreignId('assigned_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('assigned_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('primary_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained('sla_policies')->nullOnDelete();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('reopened_count')->default(0);
            $table->string('customer_phone', 32)->nullable();
            $table->string('customer_location')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->string('related_tower')->nullable();
            $table->string('related_site')->nullable();
            $table->string('related_radius_account')->nullable();
            $table->foreignId('related_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('related_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->unsignedBigInteger('related_installation_id')->nullable();
            $table->foreignId('whatsapp_conversation_id')->nullable()->constrained('whatsapp_conversations')->nullOnDelete();
            $table->string('external_reference')->nullable();
            $table->json('tags')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('customer_visible_notes')->nullable();
            $table->string('resolution_code', 64)->nullable();
            $table->text('resolution_summary')->nullable();
            $table->boolean('customer_confirmation')->default(false);
            $table->timestamp('customer_confirmed_at')->nullable();
            $table->unsignedBigInteger('major_incident_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index('primary_assignee_id');
            $table->index('response_due_at');
            $table->index('resolution_due_at');
            $table->index('ticket_number');
            $table->unique(['external_reference', 'source']);
            $table->index('type_code');
            $table->index('customer_id');
        });

        Schema::create('ticket_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['ticket_id', 'user_id']);
        });

        Schema::create('ticket_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->string('source', 32)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ticket_sla_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('response_state', 32)->default('running');
            $table->string('resolution_state', 32)->default('running');
            $table->integer('response_remaining_seconds')->nullable();
            $table->integer('resolution_remaining_seconds')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('pause_total_seconds')->default(0);
            $table->timestamp('breached_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('sla_breach_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained('sla_policies')->nullOnDelete();
            $table->string('breach_type', 32);
            $table->string('priority', 32)->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('breached_at');
            $table->unsignedInteger('overdue_seconds')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'breach_type']);
        });

        Schema::create('major_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 64)->unique();
            $table->string('title');
            $table->string('status', 32)->default('open');
            $table->string('severity', 32)->default('high');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('major_incident_id')->references('id')->on('major_incidents')->nullOnDelete();
        });

        Schema::create('major_incident_ticket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('major_incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['major_incident_id', 'ticket_id']);
        });

        Schema::create('escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticket_type_code', 64)->nullable();
            $table->string('priority', 32)->nullable();
            $table->string('department_code', 64)->nullable();
            $table->string('trigger_type', 32)->default('time');
            $table->unsignedInteger('trigger_after_minutes')->nullable();
            $table->string('from_status', 32)->nullable();
            $table->string('escalate_to_role', 64)->nullable();
            $table->string('escalate_to_department_code', 64)->nullable();
            $table->unsignedBigInteger('escalate_to_user_id')->nullable();
            $table->boolean('notify_whatsapp')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'trigger_type']);
        });

        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('escalation_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 32)->nullable();
            $table->string('status', 32)->default('open');
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('to_department_code', 64)->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'status']);
        });

        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('workflow_type', 32)->default('generic');
            $table->json('steps')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 64)->nullable();
            $table->string('priority', 32)->default('medium');
            $table->string('status', 32)->default('pending');
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('location')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('installation_id')->nullable();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->json('checklist')->nullable();
            $table->json('required_evidence')->nullable();
            $table->text('completion_notes')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('escalation_state', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index('assignee_id');
            $table->index('ticket_id');
            $table->index('due_at');
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type', 32)->default('blocking');
            $table->timestamps();
            $table->unique(['task_id', 'depends_on_task_id']);
        });

        Schema::create('task_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->string('source', 32)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('work_type', 64)->nullable();
            $table->text('internal_note')->nullable();
            $table->text('customer_visible_note')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->string('equipment_used')->nullable();
            $table->string('result', 64)->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['ticket_id', 'task_id']);
        });

        Schema::create('work_log_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });

        Schema::create('operational_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('kind', 64)->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('virus_scan_status', 32)->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('installations', function (Blueprint $table) {
            $table->id();
            $table->string('installation_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('draft');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prospect_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('address')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->string('requested_package')->nullable();
            $table->date('requested_date')->nullable();
            $table->text('coverage_notes')->nullable();
            $table->string('tower_site_candidate')->nullable();
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finance_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('noc_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('equipment_status', 32)->nullable();
            $table->decimal('installation_fee', 14, 2)->nullable();
            $table->decimal('monthly_fee_estimate', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('whatsapp_conversation_id')->nullable()->constrained('whatsapp_conversations')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('related_installation_id')->references('id')->on('installations')->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('installation_id')->references('id')->on('installations')->nullOnDelete();
        });

        Schema::create('ticket_intake_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_inbound_message_id')->unique()->constrained('whatsapp_inbound_messages')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('whatsapp_conversations')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('staff_action_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 128)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 64);
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_action_tokens');
        Schema::dropIfExists('ticket_intake_suggestions');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['installation_id']);
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['related_installation_id']);
            $table->dropForeign(['major_incident_id']);
        });

        Schema::dropIfExists('installations');
        Schema::dropIfExists('operational_attachments');
        Schema::dropIfExists('work_log_amendments');
        Schema::dropIfExists('work_logs');
        Schema::dropIfExists('task_status_transitions');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_templates');
        Schema::dropIfExists('escalations');
        Schema::dropIfExists('escalation_rules');
        Schema::dropIfExists('major_incident_ticket');
        Schema::dropIfExists('major_incidents');
        Schema::dropIfExists('sla_breach_events');
        Schema::dropIfExists('ticket_sla_states');
        Schema::dropIfExists('ticket_status_transitions');
        Schema::dropIfExists('ticket_watchers');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('installation_sequences');
        Schema::dropIfExists('task_sequences');
        Schema::dropIfExists('ticket_sequences');

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropForeign(['sla_policy_id']);
        });

        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('ticket_types');
    }
};
