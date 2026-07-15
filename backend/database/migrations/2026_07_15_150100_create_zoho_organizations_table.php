<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zoho_connection_id')->constrained('zoho_connections')->cascadeOnDelete();
            $table->string('zoho_org_id')->unique();
            $table->string('name');
            $table->string('currency_code', 10)->nullable();
            $table->boolean('is_selected')->default(false);
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_organizations');
    }
};
