<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('field_api_name');
            $table->string('field_label')->nullable();
            $table->text('field_value')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'field_api_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_custom_fields');
    }
};
