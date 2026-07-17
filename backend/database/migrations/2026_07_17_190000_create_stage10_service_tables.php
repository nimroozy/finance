<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name_en');
            $table->string('name_fa')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_access_technologies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name_en');
            $table->string('name_fa')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_sla_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('response_minutes')->default(60);
            $table->unsignedInteger('resolution_minutes')->default(240);
            $table->decimal('availability_target', 5, 2)->nullable();
            $table->json('support_hours')->nullable();
            $table->json('escalation_rules')->nullable();
            $table->json('priority_matrix')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->text('access_instructions')->nullable();
            $table->boolean('rooftop_access')->nullable();
            $table->string('power_availability', 64)->nullable();
            $table->string('building_type', 64)->nullable();
            $table->text('coverage_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'is_active']);
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('service_type_code', 64);
            $table->string('access_technology_code', 64);
            $table->unsignedInteger('download_mbps')->nullable();
            $table->unsignedInteger('upload_mbps')->nullable();
            $table->decimal('monthly_price', 14, 2)->default(0);
            $table->decimal('installation_fee', 14, 2)->default(0);
            $table->string('billing_frequency', 32)->default('monthly');
            $table->unsignedInteger('contract_period_months')->nullable();
            $table->unsignedInteger('grace_period_days')->default(0);
            $table->json('equipment_template')->nullable();
            $table->foreignId('sla_template_id')->nullable()->constrained('service_sla_templates')->nullOnDelete();
            $table->string('zoho_item_id')->nullable();
            $table->string('zoho_sync_status', 32)->default('pending');
            $table->boolean('is_active')->default(true);
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['code', 'branch_id']);
            $table->index(['service_type_code', 'is_active']);
            $table->foreign('service_type_code')->references('code')->on('service_types');
            $table->foreign('access_technology_code')->references('code')->on('service_access_technologies');
        });

        Schema::create('service_package_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('service_packages')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->date('effective_date');
            $table->decimal('monthly_price', 14, 2);
            $table->decimal('installation_fee', 14, 2)->default(0);
            $table->unsignedInteger('download_mbps')->nullable();
            $table->unsignedInteger('upload_mbps')->nullable();
            $table->json('sla_json')->nullable();
            $table->text('terms')->nullable();
            $table->json('equipment_requirements')->nullable();
            $table->timestamps();
            $table->unique(['package_id', 'version']);
        });

        Schema::create('service_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('service_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 64)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('renewal_type', 32)->default('manual');
            $table->decimal('setup_fee', 14, 2)->nullable();
            $table->decimal('recurring_fee', 14, 2)->nullable();
            $table->date('signed_date')->nullable();
            $table->string('file_path')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('zoho_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'status']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('service_number', 64)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('zoho_customer_id')->nullable();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('service_location_id')->nullable()->constrained('service_locations')->nullOnDelete();
            $table->unsignedBigInteger('installation_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->foreignId('package_id')->nullable()->constrained('service_packages')->nullOnDelete();
            $table->foreignId('package_version_id')->nullable()->constrained('service_package_versions')->nullOnDelete();
            $table->string('service_type_code', 64)->nullable();
            $table->string('access_technology_code', 64)->nullable();
            $table->string('billing_frequency', 32)->default('monthly');
            $table->decimal('mrr', 14, 2)->default(0);
            $table->decimal('installation_fee', 14, 2)->default(0);
            $table->string('currency', 8)->default('AFN');
            $table->foreignId('contract_id')->nullable()->constrained('service_contracts')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->timestamp('activation_date')->nullable();
            $table->timestamp('suspension_date')->nullable();
            $table->timestamp('reactivation_date')->nullable();
            $table->timestamp('cancellation_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('commercial_status', 32)->default('draft');
            $table->string('operational_status', 32)->default('not_provisioned');
            $table->string('billing_status', 32)->default('not_billed');
            $table->string('ownership_status', 32)->default('company');
            $table->foreignId('technical_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sales_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('support_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('tower_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('network_node')->nullable();
            $table->string('vlan', 64)->nullable();
            $table->string('static_ip', 64)->nullable();
            $table->string('public_ip', 64)->nullable();
            $table->string('private_ip', 64)->nullable();
            $table->string('pppoe_username_placeholder')->nullable();
            $table->string('circuit_id')->nullable();
            $table->string('customer_reference')->nullable();
            $table->foreignId('sla_template_id')->nullable()->constrained('service_sla_templates')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'commercial_status']);
            $table->index(['branch_id', 'operational_status']);
            $table->index(['customer_id']);
            $table->index(['installation_id']);
            $table->index(['package_id']);

            $table->foreign('installation_id')->references('id')->on('installations')->nullOnDelete();
            $table->foreign('service_type_code')->references('code')->on('service_types');
            $table->foreign('access_technology_code')->references('code')->on('service_access_technologies');
        });

        Schema::table('service_contracts', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });

        Schema::create('service_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('from_commercial', 32)->nullable();
            $table->string('to_commercial', 32)->nullable();
            $table->string('from_operational', 32)->nullable();
            $table->string('to_operational', 32)->nullable();
            $table->string('from_billing', 32)->nullable();
            $table->string('to_billing', 32)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->string('source', 64)->default('api');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['service_id', 'created_at']);
        });

        Schema::create('service_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at');
            $table->json('checklist')->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });

        Schema::create('service_suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('type', 32)->default('voluntary');
            $table->string('reason');
            $table->timestamp('effective_at');
            $table->timestamp('expected_reactivation_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('finance_reference')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('notification_status', 32)->default('pending');
            $table->timestamp('reactivated_at')->nullable();
            $table->timestamps();
            $table->index(['service_id', 'reactivated_at']);
        });

        Schema::create('service_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('reason');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('equipment_return_required')->default(true);
            $table->string('status', 32)->default('requested');
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('type', 64)->default('package_change');
            $table->json('current_values');
            $table->json('requested_values');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at')->nullable();
            $table->string('reason')->nullable();
            $table->string('finance_status', 32)->default('pending');
            $table->string('technical_status', 32)->default('pending');
            $table->string('status', 32)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['service_id', 'status']);
        });

        Schema::create('service_relocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('status', 32)->default('requested');
            $table->foreignId('old_location_id')->nullable()->constrained('service_locations')->nullOnDelete();
            $table->foreignId('new_location_id')->nullable()->constrained('service_locations')->nullOnDelete();
            $table->unsignedBigInteger('old_tower_id')->nullable();
            $table->unsignedBigInteger('new_tower_id')->nullable();
            $table->unsignedBigInteger('coverage_check_id')->nullable();
            $table->unsignedBigInteger('survey_id')->nullable();
            $table->unsignedBigInteger('quote_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('service_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->date('old_expiration')->nullable();
            $table->date('new_expiration');
            $table->unsignedInteger('term_months')->default(12);
            $table->decimal('price', 14, 2)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('zoho_invoice_ref')->nullable();
            $table->string('payment_ref')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('service_finance_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('hold_type', 64)->default('overdue');
            $table->string('reason');
            $table->string('zoho_invoice_ref')->nullable();
            $table->json('outstanding_snapshot')->nullable();
            $table->timestamp('effective_at');
            $table->timestamp('review_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['service_id', 'status']);
        });

        if (Schema::hasTable('tickets') && ! Schema::hasColumn('tickets', 'service_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->foreignId('service_id')->nullable()->after('customer_id')->constrained('services')->nullOnDelete();
                $table->index(['service_id']);
            });
        }

        if (Schema::hasTable('inventory_customer_equipment') && ! Schema::hasColumn('inventory_customer_equipment', 'service_id')) {
            Schema::table('inventory_customer_equipment', function (Blueprint $table) {
                $table->foreignId('service_id')->nullable()->after('installation_id')->constrained('services')->nullOnDelete();
                $table->index(['service_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_customer_equipment') && Schema::hasColumn('inventory_customer_equipment', 'service_id')) {
            Schema::table('inventory_customer_equipment', function (Blueprint $table) {
                $table->dropConstrainedForeignId('service_id');
            });
        }

        if (Schema::hasTable('tickets') && Schema::hasColumn('tickets', 'service_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropConstrainedForeignId('service_id');
            });
        }

        Schema::dropIfExists('service_finance_holds');
        Schema::dropIfExists('service_renewals');
        Schema::dropIfExists('service_relocations');
        Schema::dropIfExists('service_change_requests');
        Schema::dropIfExists('service_cancellations');
        Schema::dropIfExists('service_suspensions');
        Schema::dropIfExists('service_activations');
        Schema::dropIfExists('service_status_transitions');

        Schema::table('service_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });

        Schema::dropIfExists('services');
        Schema::dropIfExists('service_contracts');
        Schema::dropIfExists('service_sequences');
        Schema::dropIfExists('service_package_versions');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('service_locations');
        Schema::dropIfExists('service_sla_templates');
        Schema::dropIfExists('service_access_technologies');
        Schema::dropIfExists('service_types');
    }
};
