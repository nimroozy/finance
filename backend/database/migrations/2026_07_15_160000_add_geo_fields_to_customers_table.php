<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'area')) {
                $table->string('area')->nullable()->after('shipping_address');
            }
            if (! Schema::hasColumn('customers', 'district')) {
                $table->string('district')->nullable()->after('area');
            }
            if (! Schema::hasColumn('customers', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('district');
            }
            if (! Schema::hasColumn('customers', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('customers', 'last_payment_date')) {
                $table->date('last_payment_date')->nullable()->after('outstanding_receivable');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('customers', 'area') ? 'area' : null,
                Schema::hasColumn('customers', 'district') ? 'district' : null,
                Schema::hasColumn('customers', 'latitude') ? 'latitude' : null,
                Schema::hasColumn('customers', 'longitude') ? 'longitude' : null,
                Schema::hasColumn('customers', 'last_payment_date') ? 'last_payment_date' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
