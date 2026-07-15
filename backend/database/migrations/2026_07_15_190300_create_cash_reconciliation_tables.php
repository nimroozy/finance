<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('cashbox_id')->constrained('branch_cashboxes')->restrictOnDelete();
            $table->date('reconciliation_date');
            $table->char('currency', 3)->default('AFN');
            $table->decimal('opening_balance', 18, 4);
            $table->decimal('credits', 18, 4);
            $table->decimal('debits', 18, 4);
            $table->decimal('expected_balance', 18, 4);
            $table->decimal('counted_balance', 18, 4)->nullable();
            $table->decimal('variance_amount', 18, 4)->default(0);
            $table->string('status')->default('draft');
            $table->foreignId('run_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['cashbox_id', 'reconciliation_date']);
        });

        Schema::create('cash_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_run_id')->constrained('cash_reconciliation_runs')->cascadeOnDelete();
            $table->foreignId('cashbox_transaction_id')->nullable()->constrained('branch_cashbox_transactions')->nullOnDelete();
            $table->string('type');
            $table->decimal('amount', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_reconciliation_items');
        Schema::dropIfExists('cash_reconciliation_runs');
    }
};
