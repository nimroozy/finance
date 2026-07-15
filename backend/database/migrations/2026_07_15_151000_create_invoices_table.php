<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('zoho_invoice_id')->nullable()->unique();
            $table->string('invoice_number')->index();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('draft');
            $table->char('currency', 3)->default('AFN');
            $table->decimal('total', 18, 4)->default(0);
            $table->decimal('amount_paid', 18, 4)->default(0);
            $table->decimal('credits_applied', 18, 4)->default(0);
            $table->decimal('balance', 18, 4)->default(0);
            $table->json('reporting_tags')->nullable();
            $table->timestamp('zoho_modified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->index('branch_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('balance');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
