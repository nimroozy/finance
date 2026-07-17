<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ui_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('locale', 8)->nullable();
            $table->string('theme', 16)->default('system');
            $table->string('default_app_id')->nullable();
            $table->json('favorite_app_ids');
            $table->json('recent_app_ids');
            $table->string('date_format')->nullable();
            $table->string('calendar_system', 32)->nullable();
            $table->json('collapsed_nav_groups')->nullable();
            $table->json('bottom_nav_overrides')->nullable();
            $table->json('notification_prefs')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ui_preferences');
    }
};
