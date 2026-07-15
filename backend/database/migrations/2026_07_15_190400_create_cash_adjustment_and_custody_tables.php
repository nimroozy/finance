<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_adjustment_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('cashbox_id')->nullable()->constrained('branch_cashboxes')->nullOnDelete();
            $table->char('currency', 3)->default('AFN');
            $table->decimal('amount', 18, 4);
            $table->string('direction');
            $table->string('status')->default('pending');
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_adjustment_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_request_id')->constrained('cash_adjustment_requests')->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->string('decision');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('custody_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained('payments')->restrictOnDelete();
            $table->foreignId('handover_id')->nullable()->constrained('cash_handover_requests')->nullOnDelete();
            $table->foreignId('reversal_id')->nullable()->constrained('payment_reversals')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('status')->default('pending_review');
            $table->text('reason');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custody_conflicts');
        Schema::dropIfExists('cash_adjustment_approvals');
        Schema::dropIfExists('cash_adjustment_requests');
    }
};
