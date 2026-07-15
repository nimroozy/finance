<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('collection_routes')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('customer_assignments')->nullOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('status')->default('pending');
            $table->foreignId('visit_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['route_id', 'sequence']);
            $table->index(['route_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_route_stops');
    }
};
