<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('customer_assignments')->cascadeOnDelete();
            $table->string('event');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_collector_id')->nullable()->constrained('collectors')->nullOnDelete();
            $table->foreignId('to_collector_id')->nullable()->constrained('collectors')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_assignment_history');
    }
};
