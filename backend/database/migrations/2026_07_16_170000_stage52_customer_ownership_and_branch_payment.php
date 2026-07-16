<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_collector_ownerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 32)->default('active'); // active|suspended|ended|transferred
            $table->string('reason')->nullable();
            $table->string('area')->nullable();
            $table->string('route_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['collector_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        // One active permanent ownership per customer (service also enforces; PG partial unique).
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX customer_collector_ownerships_one_active ON customer_collector_ownerships (customer_id) WHERE is_active = true AND status = \'active\'');
        }

        Schema::create('customer_collector_ownership_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ownership_id')->nullable()->constrained('customer_collector_ownerships')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('collectors')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 64);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('temporary_collection_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('temporary_collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->foreignId('permanent_collector_id')->nullable()->constrained('collectors')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 32)->default('active'); // active|expired|cancelled|completed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['temporary_collector_id', 'status']);
            $table->index(['end_date', 'status']);
        });

        Schema::create('temporary_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temporary_assignment_id')->nullable()->constrained('temporary_collection_assignments')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 64);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ownership_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('from_collector_id')->nullable()->constrained('collectors')->nullOnDelete();
            $table->foreignId('to_collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->date('effective_date');
            $table->string('reason');
            $table->string('status', 32)->default('pending'); // pending|approved|rejected|applied|cancelled
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });

        Schema::create('ownership_transfer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_request_id')->constrained('ownership_transfer_requests')->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 16); // approved|rejected
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_work_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('effective_collector_id')->nullable()->constrained('collectors')->nullOnDelete();
            $table->string('ownership_source', 32)->default('unassigned'); // permanent|temporary|manual|unassigned|conflict
            $table->decimal('total_open_balance', 18, 4)->default(0);
            $table->unsignedInteger('open_invoice_count')->default(0);
            $table->date('oldest_due_date')->nullable();
            $table->integer('days_overdue')->default(0);
            $table->date('last_payment_date')->nullable();
            $table->timestamp('last_visit_at')->nullable();
            $table->string('promise_status')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('work_status', 32)->default('open'); // open|in_progress|paused|closed
            $table->timestamp('last_routed_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'ownership_source']);
            $table->index(['effective_collector_id', 'work_status']);
            $table->index(['days_overdue']);
        });

        Schema::create('branch_zoho_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained('branches')->cascadeOnDelete();
            $table->string('zoho_location_id')->nullable();
            $table->string('zoho_account_id');
            $table->string('zoho_account_name')->nullable();
            $table->string('zoho_payment_mode_id')->nullable();
            $table->string('zoho_payment_mode_name')->nullable();
            $table->string('currency', 3)->default('AFN');
            $table->string('receipt_prefix', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('readiness_status', 32)->default('missing_account');
            $table->string('validation_status', 32)->default('pending');
            $table->timestamp('last_validated_at')->nullable();
            $table->text('validation_message')->nullable();
            $table->unsignedInteger('mapping_version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['zoho_account_id']);
            $table->index(['readiness_status']);
        });

        Schema::create('branch_payment_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('account_mapping_id')->nullable()->constrained('branch_zoho_account_mappings')->nullOnDelete();
            $table->boolean('require_ready_for_live')->default(true);
            $table->boolean('allow_dry_run_without_mapping')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('ownership_sync_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('source', 64)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('recurring_invoice_routing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('collectors')->nullOnDelete();
            $table->string('ownership_source', 32)->nullable();
            $table->string('result', 32); // routed|unassigned|conflict|skipped
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
            $table->index(['invoice_id']);
        });

        Schema::create('ownership_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('conflict_type', 64);
            $table->string('status', 32)->default('open'); // open|resolved|ignored
            $table->json('details')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'conflict_type']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('branch_payment_configuration_id')->nullable()->after('branch_id');
            $table->string('zoho_account_id_snapshot')->nullable()->after('zoho_payment_id');
            $table->string('zoho_account_name_snapshot')->nullable();
            $table->string('zoho_location_id_used')->nullable();
            $table->string('zoho_payment_mode_used')->nullable();
            $table->unsignedInteger('mapping_version_used')->nullable();
            $table->timestamp('mapping_validated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'branch_payment_configuration_id',
                'zoho_account_id_snapshot',
                'zoho_account_name_snapshot',
                'zoho_location_id_used',
                'zoho_payment_mode_used',
                'mapping_version_used',
                'mapping_validated_at',
            ]);
        });
        Schema::dropIfExists('ownership_conflicts');
        Schema::dropIfExists('recurring_invoice_routing_logs');
        Schema::dropIfExists('ownership_sync_events');
        Schema::dropIfExists('branch_payment_configurations');
        Schema::dropIfExists('branch_zoho_account_mappings');
        Schema::dropIfExists('customer_work_queues');
        Schema::dropIfExists('ownership_transfer_approvals');
        Schema::dropIfExists('ownership_transfer_requests');
        Schema::dropIfExists('temporary_assignment_history');
        Schema::dropIfExists('temporary_collection_assignments');
        Schema::dropIfExists('customer_collector_ownership_history');
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS customer_collector_ownerships_one_active');
        }
        Schema::dropIfExists('customer_collector_ownerships');
    }
};
