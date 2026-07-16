<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('assigned');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(true);
            $table->string('priority')->default('normal');
            $table->string('assignment_source')->default('manual');
            $table->decimal('debt_snapshot_outstanding', 18, 4)->default(0);
            $table->decimal('debt_snapshot_overdue', 18, 4)->nullable();
            $table->unsignedInteger('debt_snapshot_invoice_count')->default(0);
            $table->char('debt_snapshot_currency', 3)->default('AFN');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reassigned_from_id')->nullable()->constrained('customer_assignments')->nullOnDelete();
            $table->unsignedBigInteger('bulk_operation_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['collector_id', 'is_active']);
            $table->index(['customer_id', 'is_active']);
            $table->index('status');
        });

        // PostgreSQL partial unique: one active assignment per customer.
        // SQLite tests rely on application-level lockForUpdate enforcement.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX customer_assignments_one_active_per_customer ON customer_assignments (customer_id) WHERE is_active = true AND deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS customer_assignments_one_active_per_customer');
        }

        Schema::dropIfExists('customer_assignments');
    }
};
