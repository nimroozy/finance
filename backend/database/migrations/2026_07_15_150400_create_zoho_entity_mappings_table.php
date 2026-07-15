<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_entity_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('local_id');
            $table->string('zoho_id');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'zoho_id']);
            $table->index(['entity_type', 'local_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_entity_mappings');
    }
};
