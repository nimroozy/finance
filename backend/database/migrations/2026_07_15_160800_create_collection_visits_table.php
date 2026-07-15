<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('customer_assignments')->nullOnDelete();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->foreignId('route_stop_id')->nullable()->constrained('collection_route_stops')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('visited_at');
            $table->string('outcome');
            $table->text('notes')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('customer_latitude', 10, 7)->nullable();
            $table->decimal('customer_longitude', 10, 7)->nullable();
            $table->decimal('distance_meters', 12, 2)->nullable();
            $table->string('gps_risk_level')->nullable();
            $table->text('correction_note')->nullable();
            $table->foreignId('correction_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('correction_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'visited_at']);
            $table->index(['collector_id', 'visited_at']);
            $table->index(['customer_id', 'visited_at']);
            $table->index('outcome');
        });

        Schema::table('collection_route_stops', function (Blueprint $table) {
            $table->foreign('visit_id')
                ->references('id')
                ->on('collection_visits')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collection_route_stops', function (Blueprint $table) {
            $table->dropForeign(['visit_id']);
        });

        Schema::dropIfExists('collection_visits');
    }
};
