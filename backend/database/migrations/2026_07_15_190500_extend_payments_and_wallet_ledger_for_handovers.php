<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('handover_status')->nullable()->default('pending_handover')->after('status')->index();
            $table->foreignId('cash_handover_request_id')->nullable()
                ->after('receipt_id')->constrained('cash_handover_requests')->nullOnDelete();
        });

        Schema::table('collector_wallet_transactions', function (Blueprint $table) {
            $table->foreignId('handover_id')->nullable()
                ->after('reversal_id')->constrained('cash_handover_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collector_wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handover_id');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_handover_request_id');
            $table->dropColumn('handover_status');
        });
    }
};
