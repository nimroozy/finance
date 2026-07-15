<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('zoho_creditnote_id')->nullable()->unique();
            $table->string('number')->nullable()->index();
            $table->decimal('total', 18, 4)->default(0);
            $table->decimal('balance', 18, 4)->default(0);
            $table->string('status')->default('draft');
            $table->date('creditnote_date')->nullable();
            $table->timestamp('zoho_modified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
