<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name_en');
            $table->string('name_fa')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('crm_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('type', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('branch_id');
        });

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('crm_organizations')->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('branch_id');
            $table->index('phone');
        });

        Schema::create('crm_lead_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('crm_quote_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('crm_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('source', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('crm_organizations')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('company')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->string('source_code', 64)->nullable();
            $table->string('campaign')->nullable();
            $table->string('referral')->nullable();
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('lead_value', 14, 2)->nullable();
            $table->decimal('estimated_mrr', 14, 2)->nullable();
            $table->decimal('expected_installation_fee', 14, 2)->nullable();
            $table->string('interested_package')->nullable();
            $table->string('customer_type', 32)->nullable();
            $table->string('pipeline_stage', 64)->default('lead');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->unsignedTinyInteger('probability')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->string('competitor')->nullable();
            $table->string('lost_reason')->nullable();
            $table->string('win_reason')->nullable();
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedBigInteger('installation_id')->nullable();
            $table->decimal('opportunity_value', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'pipeline_stage']);
            $table->index('salesperson_id');
            $table->index('next_follow_up_at');
            $table->index('phone');
            $table->index('lead_number');
            $table->foreign('installation_id')->references('id')->on('installations')->nullOnDelete();
            $table->foreign('source_code')->references('code')->on('crm_lead_sources')->nullOnDelete();
        });

        Schema::create('crm_lead_stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->string('from_stage', 64)->nullable();
            $table->string('to_stage', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['lead_id', 'created_at']);
        });

        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('stage', 64)->default('qualified');
            $table->unsignedTinyInteger('probability')->nullable();
            $table->decimal('value', 14, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lost_reason')->nullable();
            $table->string('win_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'stage']);
            $table->index('salesperson_id');
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('crm_opportunities')->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('type', 64);
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at')->nullable();
            $table->boolean('customer_visible')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'lead_id']);
            $table->index('performed_at');
        });

        Schema::create('crm_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at');
            $table->string('status', 32)->default('pending');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_at']);
            $table->index('assigned_to');
        });

        Schema::create('crm_coverage_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->string('result', 64);
            $table->string('candidate_tower')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('engineer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signal_estimate')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->index('lead_id');
        });

        Schema::create('crm_site_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->string('los_status', 64)->nullable();
            $table->string('signal_estimate')->nullable();
            $table->string('mounting_location')->nullable();
            $table->string('power_availability')->nullable();
            $table->text('equipment_recommendation')->nullable();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('survey_date')->nullable();
            $table->boolean('customer_approved')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'lead_id']);
            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
        });

        Schema::create('crm_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number', 64)->unique();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('monthly_package', 14, 2)->nullable();
            $table->decimal('installation_fee', 14, 2)->nullable();
            $table->decimal('discount', 14, 2)->nullable();
            $table->json('equipment_json')->nullable();
            $table->decimal('taxes', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status', 32)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status']);
            $table->index('lead_id');
        });

        Schema::create('crm_sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedInteger('leads_target')->nullable();
            $table->unsignedInteger('qualified_target')->nullable();
            $table->unsignedInteger('won_target')->nullable();
            $table->decimal('revenue_target', 14, 2)->nullable();
            $table->decimal('installation_fee_target', 14, 2)->nullable();
            $table->decimal('mrr_target', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'team_id', 'user_id', 'period_year', 'period_month'], 'crm_sales_targets_unique');
            $table->index(['branch_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sales_targets');
        Schema::dropIfExists('crm_quotations');
        Schema::dropIfExists('crm_site_surveys');
        Schema::dropIfExists('crm_coverage_checks');
        Schema::dropIfExists('crm_follow_ups');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_lead_stage_transitions');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_campaigns');
        Schema::dropIfExists('crm_quote_sequences');
        Schema::dropIfExists('crm_lead_sequences');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_organizations');
        Schema::dropIfExists('crm_lead_sources');
    }
};
