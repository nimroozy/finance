<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('zoho_contact_id')->nullable()->unique();
            $table->string('customer_number')->nullable()->index();
            $table->string('contact_name');
            $table->string('company_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('email')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->char('currency', 3)->default('AFN');
            $table->decimal('outstanding_receivable', 18, 4)->default(0);
            $table->string('payment_terms')->nullable();
            $table->string('status')->default('active'); // active|inactive|archived|unmapped
            $table->json('reporting_tags')->nullable();
            $table->timestamp('zoho_created_at')->nullable();
            $table->timestamp('zoho_modified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('pending');
            $table->boolean('is_unmapped')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('branch_id');
            $table->index('status');
            $table->index('outstanding_receivable');
            $table->index('is_unmapped');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
