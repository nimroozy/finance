<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('field_api_name');
            $table->string('field_label')->nullable();
            $table->text('field_value')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'field_api_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_custom_fields');
    }
};
