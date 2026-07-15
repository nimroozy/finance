<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reconciliation_records', function (Blueprint $table) {
            $table->id();
            $table->date('reconciliation_date')->index();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('status')->default('completed');
            $table->unsignedInteger('local_confirmed_count')->default(0);
            $table->unsignedInteger('zoho_synced_count')->default(0);
            $table->unsignedInteger('sync_failed_count')->default(0);
            $table->unsignedInteger('reversed_count')->default(0);
            $table->decimal('local_confirmed_amount', 18, 4)->default(0);
            $table->decimal('zoho_synced_amount', 18, 4)->default(0);
            $table->decimal('variance_amount', 18, 4)->default(0);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->unique(['reconciliation_date', 'branch_id']);
        });

        Schema::create('payment_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('conflict_type');
            $table->string('status')->default('open')->index();
            $table->text('description')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('zoho_payment_mode_mappings', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('local_method')
                ->constrained('payment_methods')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('payment_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('zoho_payment_mode_mappings', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn(['payment_method_id', 'is_active']);
        });
        Schema::dropIfExists('payment_conflicts');
        Schema::dropIfExists('payment_reconciliation_records');
    }
};
