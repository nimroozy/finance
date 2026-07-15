<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_cashbox_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('transfer_number')->unique();
            $table->foreignId('from_cashbox_id')->nullable()->constrained('branch_cashboxes')->nullOnDelete();
            $table->foreignId('to_cashbox_id')->nullable()->constrained('branch_cashboxes')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('type')->default('cashbox_transfer');
            $table->char('currency', 3)->default('AFN');
            $table->decimal('amount', 18, 4);
            $table->string('status')->index();
            $table->string('bank_name')->nullable();
            $table->string('bank_reference')->nullable();
            $table->date('deposited_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('branch_cashbox_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('branch_cashbox_transfers')->cascadeOnDelete();
            $table->foreignId('cashbox_transaction_id')->nullable()->constrained('branch_cashbox_transactions')->nullOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('type');
            $table->timestamps();
        });

        Schema::create('branch_cashbox_transfer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('branch_cashbox_transfers')->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->string('decision');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('branch_cashbox_transfer_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->unique()->constrained('branch_cashbox_transfers')->cascadeOnDelete();
            $table->foreignId('reversed_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_cashbox_transfer_reversals');
        Schema::dropIfExists('branch_cashbox_transfer_approvals');
        Schema::dropIfExists('branch_cashbox_transfer_items');
        Schema::dropIfExists('branch_cashbox_transfers');
    }
};
