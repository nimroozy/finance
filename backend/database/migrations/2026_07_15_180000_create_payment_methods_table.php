<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_fa')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('collector_allowed')->default(true);
            $table->boolean('requires_reference')->default(false);
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('affects_cash_wallet')->default(false);
            $table->boolean('receipt_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('payment_methods')->insert([
            [
                'code' => 'cash',
                'name_en' => 'Cash',
                'name_fa' => 'نقد',
                'is_active' => true,
                'collector_allowed' => true,
                'requires_reference' => false,
                'requires_evidence' => false,
                'affects_cash_wallet' => true,
                'receipt_enabled' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'bank_transfer',
                'name_en' => 'Bank Transfer',
                'name_fa' => 'حواله بانکی',
                'is_active' => true,
                'collector_allowed' => true,
                'requires_reference' => true,
                'requires_evidence' => false,
                'affects_cash_wallet' => false,
                'receipt_enabled' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'card',
                'name_en' => 'Card',
                'name_fa' => 'کارت',
                'is_active' => true,
                'collector_allowed' => true,
                'requires_reference' => true,
                'requires_evidence' => false,
                'affects_cash_wallet' => false,
                'receipt_enabled' => true,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'mobile_money',
                'name_en' => 'Mobile Money',
                'name_fa' => 'پول موبایل',
                'is_active' => true,
                'collector_allowed' => true,
                'requires_reference' => true,
                'requires_evidence' => false,
                'affects_cash_wallet' => false,
                'receipt_enabled' => true,
                'sort_order' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'cheque',
                'name_en' => 'Cheque',
                'name_fa' => 'چک',
                'is_active' => true,
                'collector_allowed' => true,
                'requires_reference' => true,
                'requires_evidence' => true,
                'affects_cash_wallet' => false,
                'receipt_enabled' => true,
                'sort_order' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'other',
                'name_en' => 'Other',
                'name_fa' => 'سایر',
                'is_active' => true,
                'collector_allowed' => true,
                'requires_reference' => false,
                'requires_evidence' => false,
                'affects_cash_wallet' => false,
                'receipt_enabled' => true,
                'sort_order' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
