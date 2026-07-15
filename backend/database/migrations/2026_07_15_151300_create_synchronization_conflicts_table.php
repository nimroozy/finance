<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synchronization_conflicts', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('local_id')->nullable();
            $table->string('zoho_id')->nullable();
            $table->string('conflict_type');
            $table->json('details')->nullable();
            $table->string('status')->default('open'); // open|resolved
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synchronization_conflicts');
    }
};
