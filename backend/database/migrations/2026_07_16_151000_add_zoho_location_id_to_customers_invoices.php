<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'zoho_location_id')) {
                $table->string('zoho_location_id', 64)->nullable()->after('branch_id')->index();
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'zoho_location_id')) {
                $table->string('zoho_location_id', 64)->nullable()->after('branch_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'zoho_location_id')) {
                $table->dropColumn('zoho_location_id');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'zoho_location_id')) {
                $table->dropColumn('zoho_location_id');
            }
        });
    }
};
