<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->char('currency', 3)->default('AFN');
            $table->decimal('balance', 18, 4)->default(0);
            $table->decimal('pending_handover_balance', 18, 4)->default(0);
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamps();

            $table->unique(['collector_id', 'branch_id', 'currency']);
        });

        Schema::create('collector_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_wallet_id')->constrained('collector_wallets')->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('collectors')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('reversal_id')->nullable()->constrained('payment_reversals')->nullOnDelete();
            $table->string('type');
            $table->decimal('amount', 18, 4);
            $table->decimal('balance_before', 18, 4);
            $table->decimal('balance_after', 18, 4);
            $table->char('currency', 3)->default('AFN');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['collector_wallet_id', 'created_at']);
            $table->index(['payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_wallet_transactions');
        Schema::dropIfExists('collector_wallets');
    }
};
