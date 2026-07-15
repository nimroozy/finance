<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_bulk_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('strategy');
            $table->string('status')->default('preview');
            $table->json('preview_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->unsignedInteger('selected_count')->default(0);
            $table->decimal('total_outstanding', 18, 4)->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('customer_assignments', function (Blueprint $table) {
            $table->foreign('bulk_operation_id')
                ->references('id')
                ->on('assignment_bulk_operations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_assignments', function (Blueprint $table) {
            $table->dropForeign(['bulk_operation_id']);
        });

        Schema::dropIfExists('assignment_bulk_operations');
    }
};
