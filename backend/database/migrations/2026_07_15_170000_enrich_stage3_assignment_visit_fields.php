<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_assignments', function (Blueprint $table) {
            $table->date('planned_visit_date')->nullable()->after('due_date');
            $table->timestamp('last_visit_at')->nullable()->after('first_viewed_at');
            $table->unsignedInteger('reassignment_count')->default(0)->after('reassigned_from_id');
            $table->text('manager_notes')->nullable()->after('notes');
            $table->text('collector_instructions')->nullable()->after('manager_notes');
            $table->string('assignment_reason')->nullable()->after('assignment_source');
            $table->date('oldest_due_date_snapshot')->nullable()->after('debt_snapshot_invoice_count');
            $table->unsignedInteger('days_overdue_snapshot')->nullable()->after('oldest_due_date_snapshot');
            $table->boolean('allow_shared_assignment')->default(false)->after('is_primary');

            $table->index('planned_visit_date');
            $table->index('last_visit_at');
        });

        Schema::table('collection_visits', function (Blueprint $table) {
            $table->decimal('gps_accuracy', 12, 2)->nullable()->after('longitude');
            $table->string('gps_permission_state')->nullable()->after('gps_accuracy');
            $table->string('gps_source')->nullable()->after('gps_permission_state');
            $table->timestamp('gps_captured_at')->nullable()->after('gps_source');
            $table->string('device_info', 500)->nullable()->after('gps_captured_at');
            $table->string('ip_address', 45)->nullable()->after('device_info');
            $table->string('contact_status')->nullable()->after('notes');
            $table->string('person_met')->nullable()->after('contact_status');
            $table->boolean('escalation_flag')->default(false)->after('follow_up_date');
            $table->string('origin')->default('online')->after('escalation_flag');
            $table->boolean('signature_placeholder')->default(false)->after('origin');

            $table->index('escalation_flag');
            $table->index('gps_risk_level');
        });
    }

    public function down(): void
    {
        Schema::table('customer_assignments', function (Blueprint $table) {
            $table->dropIndex(['planned_visit_date']);
            $table->dropIndex(['last_visit_at']);
            $table->dropColumn([
                'planned_visit_date',
                'last_visit_at',
                'reassignment_count',
                'manager_notes',
                'collector_instructions',
                'assignment_reason',
                'oldest_due_date_snapshot',
                'days_overdue_snapshot',
                'allow_shared_assignment',
            ]);
        });

        Schema::table('collection_visits', function (Blueprint $table) {
            $table->dropIndex(['escalation_flag']);
            $table->dropIndex(['gps_risk_level']);
            $table->dropColumn([
                'gps_accuracy',
                'gps_permission_state',
                'gps_source',
                'gps_captured_at',
                'device_info',
                'ip_address',
                'contact_status',
                'person_met',
                'escalation_flag',
                'origin',
                'signature_placeholder',
            ]);
        });
    }
};
