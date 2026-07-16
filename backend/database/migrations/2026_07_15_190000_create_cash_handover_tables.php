<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_cashboxes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->char('currency', 3)->default('AFN');
            $table->string('name')->default('Main cashbox');
            $table->decimal('balance', 18, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'currency', 'name']);
        });

        Schema::create('cash_handover_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('handover_number')->nullable()->unique();
            $table->foreignId('collector_id')->constrained('collectors')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->char('currency', 3)->default('AFN');
            $table->decimal('declared_amount', 18, 4)->default(0);
            $table->decimal('expected_wallet_amount', 18, 4)->default(0);
            $table->decimal('selected_payment_total', 18, 4)->default(0);
            $table->decimal('counted_amount', 18, 4)->nullable();
            $table->decimal('approved_amount', 18, 4)->nullable();
            $table->decimal('variance_amount', 18, 4)->default(0);
            $table->string('variance_type')->nullable();
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('cashbox_id')->nullable()->constrained('branch_cashboxes')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->index(['collector_id', 'status']);
        });

        Schema::create('cash_handover_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_id')->constrained('cash_handover_requests')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->decimal('payment_amount', 18, 4);
            $table->decimal('approved_amount', 18, 4)->nullable();
            $table->string('item_status')->default('selected')->index();
            $table->boolean('is_active')->default(true);
            $table->string('receipt_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['payment_id', 'is_active']);
            $table->index(['handover_id', 'item_status']);
        });

        Schema::create('cash_handover_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_id')->constrained('cash_handover_requests')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['handover_id', 'created_at']);
        });

        Schema::create('cash_handover_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_id')->constrained('cash_handover_requests')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('decision');
            $table->decimal('counted_amount', 18, 4)->nullable();
            $table->decimal('approved_amount', 18, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_handover_variances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_id')->constrained('cash_handover_requests')->cascadeOnDelete();
            $table->decimal('expected_amount', 18, 4);
            $table->decimal('actual_amount', 18, 4);
            $table->decimal('variance_amount', 18, 4);
            $table->string('variance_type');
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_handover_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_id')->unique()->constrained('cash_handover_requests')->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3);
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_handover_receipts');
        Schema::dropIfExists('cash_handover_variances');
        Schema::dropIfExists('cash_handover_reviews');
        Schema::dropIfExists('cash_handover_status_history');
        Schema::dropIfExists('cash_handover_items');
        Schema::dropIfExists('cash_handover_requests');
        Schema::dropIfExists('branch_cashboxes');
    }
};
