<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('AFN');
            $table->string('status')->default('active')->index();
            $table->decimal('invoice_balance_before', 18, 4)->nullable();
            $table->decimal('invoice_balance_after', 18, 4)->nullable();
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id']);
            $table->index('invoice_id');
        });

        Schema::create('payment_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at']);
        });

        Schema::create('payment_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('request_hash');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->json('response_json')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'key']);
        });

        Schema::create('payment_sync_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('status');
            $table->string('zoho_payment_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_dry_run')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_sync_attempts');
        Schema::dropIfExists('payment_idempotency_keys');
        Schema::dropIfExists('payment_status_history');
        Schema::dropIfExists('payment_allocations');
    }
};
