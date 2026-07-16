<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custody_conflicts', function (Blueprint $table) {
            $table->string('zoho_void_status')->default('not_required')->after('status');
            $table->text('zoho_void_error')->nullable()->after('zoho_void_status');
            $table->foreignId('cashbox_debit_transaction_id')->nullable()->after('zoho_void_error')
                ->constrained('branch_cashbox_transactions')->nullOnDelete();
            $table->boolean('cashbox_insufficient')->default(false)->after('cashbox_debit_transaction_id');
            $table->text('manual_review_reason')->nullable()->after('cashbox_insufficient');
            $table->boolean('critical_alert')->default(false)->after('manual_review_reason');
            $table->text('critical_alert_message')->nullable()->after('critical_alert');
            $table->timestamp('local_completed_at')->nullable()->after('critical_alert_message');
            $table->timestamp('zoho_completed_at')->nullable()->after('local_completed_at');
            $table->string('threshold_tier')->nullable()->after('zoho_completed_at');
            $table->decimal('approved_amount', 18, 4)->nullable()->after('threshold_tier');
            $table->json('details')->nullable()->after('approved_amount');
            $table->foreignId('payment_reconciliation_record_id')->nullable()->after('details')
                ->constrained('payment_reconciliation_records')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('balance_sync_status')->default('synced')->after('sync_status');
            $table->text('balance_sync_warning')->nullable()->after('balance_sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('custody_conflicts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_reconciliation_record_id');
            $table->dropConstrainedForeignId('cashbox_debit_transaction_id');
            $table->dropColumn([
                'zoho_void_status',
                'zoho_void_error',
                'cashbox_insufficient',
                'manual_review_reason',
                'critical_alert',
                'critical_alert_message',
                'local_completed_at',
                'zoho_completed_at',
                'threshold_tier',
                'approved_amount',
                'details',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['balance_sync_status', 'balance_sync_warning']);
        });
    }
};
