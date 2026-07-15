<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_daily_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('activity_date');
            $table->unsignedInteger('visits_count')->default(0);
            $table->unsignedInteger('assignments_completed')->default(0);
            $table->unsignedInteger('promises_created')->default(0);
            $table->unsignedInteger('routes_completed')->default(0);
            $table->decimal('distance_traveled_meters', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['collector_id', 'activity_date']);
            $table->index(['branch_id', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_daily_activity');
    }
};
