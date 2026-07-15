<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_payment_mode_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_payment_mode_id');
            $table->string('name');
            $table->string('local_method')->nullable();
            $table->timestamps();

            $table->unique('zoho_payment_mode_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_payment_mode_mappings');
    }
};
