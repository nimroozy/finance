<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('organization_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('accounts_domain');
            $table->string('api_domain');
            $table->string('data_center')->default('us');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('scopes')->nullable();
            $table->string('status')->default('disconnected'); // connected|disconnected|error
            $table->timestamp('last_connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_connections');
    }
};
