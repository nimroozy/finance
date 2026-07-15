<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promise_to_pay', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('customer_assignments')->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('collection_visits')->nullOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('collectors')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('AFN');
            $table->date('promised_date');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('promise_to_pay')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['promised_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promise_to_pay');
    }
};
