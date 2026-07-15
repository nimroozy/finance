<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_reporting_tag_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('tag_id');
            $table->string('tag_option_id');
            $table->string('tag_name');
            $table->string('option_name');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tag_id', 'tag_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_reporting_tag_mappings');
    }
};
