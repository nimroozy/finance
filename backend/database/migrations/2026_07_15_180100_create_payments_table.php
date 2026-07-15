<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('payment_reference')->nullable()->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('collectors')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('customer_assignments')->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('collection_visits')->nullOnDelete();
            $table->foreignId('promise_id')->nullable()->constrained('promise_to_pay')->nullOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->char('currency', 3)->default('AFN');
            $table->decimal('amount', 18, 4);
            $table->string('status')->index();
            $table->string('zoho_sync_status')->index();
            $table->string('zoho_payment_id')->nullable()->index();
            $table->string('zoho_reference')->nullable();
            $table->unsignedInteger('sync_attempts')->default(0);
            $table->text('last_sync_error')->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('external_reference')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy', 10, 2)->nullable();
            $table->string('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('receipt_id')->nullable()->index();
            $table->timestamp('draft_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['collector_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
