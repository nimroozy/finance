<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_cancellations')) {
            $addRecoveredAt = ! Schema::hasColumn('service_cancellations', 'equipment_recovered_at');
            $addRecoveredBy = ! Schema::hasColumn('service_cancellations', 'equipment_recovered_by');
            if ($addRecoveredAt || $addRecoveredBy) {
                Schema::table('service_cancellations', function (Blueprint $table) use ($addRecoveredAt, $addRecoveredBy) {
                    if ($addRecoveredAt) {
                        $table->timestamp('equipment_recovered_at')->nullable()->after('equipment_return_required');
                    }
                    if ($addRecoveredBy) {
                        $table->foreignId('equipment_recovered_by')->nullable()
                            ->constrained('users')->nullOnDelete();
                    }
                });
            }
        }

        if (Schema::hasTable('service_contracts') && ! Schema::hasColumn('service_contracts', 'branch_id')) {
            Schema::table('service_contracts', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('customer_id')
                    ->constrained('branches')->nullOnDelete();
            });
        }

        if (Schema::hasTable('service_sla_templates') && ! Schema::hasColumn('service_sla_templates', 'code')) {
            Schema::table('service_sla_templates', function (Blueprint $table) {
                $table->string('code', 64)->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_cancellations') && Schema::hasColumn('service_cancellations', 'equipment_recovered_by')) {
            Schema::table('service_cancellations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('equipment_recovered_by');
            });
        }
        if (Schema::hasTable('service_cancellations') && Schema::hasColumn('service_cancellations', 'equipment_recovered_at')) {
            Schema::table('service_cancellations', function (Blueprint $table) {
                $table->dropColumn('equipment_recovered_at');
            });
        }

        if (Schema::hasTable('service_contracts') && Schema::hasColumn('service_contracts', 'branch_id')) {
            Schema::table('service_contracts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        if (Schema::hasTable('service_sla_templates') && Schema::hasColumn('service_sla_templates', 'code')) {
            Schema::table('service_sla_templates', function (Blueprint $table) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }
    }
};
