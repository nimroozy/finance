<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('inventory_product_categories')->nullOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name_en');
            $table->string('name_fa')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('sku', 64)->nullable()->index();
            $table->string('name_en');
            $table->string('name_fa')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('inventory_product_categories')->nullOnDelete();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('uom', 32)->default('ea');
            $table->string('barcode')->nullable()->index();
            $table->string('tracking_mode', 32)->default('quantity'); // serialized|quantity|batch|non_stock_service
            $table->boolean('is_purchase')->default(true);
            $table->boolean('is_sales')->default(true);
            $table->boolean('is_installable')->default(false);
            $table->boolean('is_fixed_asset_eligible')->default(false);
            $table->boolean('is_consumable')->default(false);
            $table->boolean('is_returnable')->default(true);
            $table->boolean('is_company_owned')->default(true);
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->decimal('sales_price', 14, 2)->nullable();
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->decimal('reorder_qty', 14, 3)->nullable();
            $table->unsignedInteger('warranty_months')->nullable();
            $table->string('zoho_item_id')->nullable()->index();
            $table->string('zoho_sync_status', 32)->default('pending');
            $table->string('zoho_idempotency_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'is_active']);
            $table->index('tracking_mode');
        });

        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('type', 32); // warehouse|sales_store|technical_store|repair_store|office|employee_custody|vehicle|tower|network_site|customer_site|in_transit|damaged|quarantine|scrap
            $table->string('name');
            $table->text('address')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->foreignId('custodian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('allow_sales')->default(false);
            $table->boolean('allow_receiving')->default(false);
            $table->boolean('allow_dispatch')->default(false);
            $table->boolean('allow_negative')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'type']);
        });

        Schema::create('inventory_sites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('address')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->string('site_type', 64)->nullable();
            $table->string('landlord')->nullable();
            $table->date('lease_start')->nullable();
            $table->date('lease_end')->nullable();
            $table->decimal('rent', 14, 2)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('power_source')->nullable();
            $table->string('power_capacity')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('inventory_towers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->foreignId('site_id')->constrained('inventory_sites')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('height_m', 8, 2)->nullable();
            $table->string('structure_type', 64)->nullable();
            $table->string('owner')->nullable();
            $table->string('operational_status', 32)->default('active');
            $table->date('installation_date')->nullable();
            $table->date('last_inspection_date')->nullable();
            $table->date('next_inspection_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'operational_status']);
        });

        Schema::create('inventory_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('payment_terms')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('zoho_vendor_id')->nullable()->index();
            $table->string('zoho_sync_status', 32)->default('pending');
            $table->string('zoho_idempotency_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_txn_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('inventory_equipment_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('inventory_transfer_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('inventory_po_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('inventory_sale_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('inventory_asset_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('inventory_reservation_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        Schema::create('inventory_equipment', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_number', 64)->unique();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->string('serial_number');
            $table->string('mac_address', 64)->nullable()->index();
            $table->string('barcode')->nullable()->index();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('custodian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ownership', 32)->default('company'); // company|customer|leased
            $table->string('condition', 32)->default('new'); // new|good|fair|damaged|scrap
            $table->string('status', 32)->default('in_stock'); // in_stock|reserved|in_transit|installed|custody|repair|sold|scrapped|lost
            $table->foreignId('supplier_id')->nullable()->constrained('inventory_suppliers')->nullOnDelete();
            $table->decimal('purchase_cost', 14, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_start')->nullable();
            $table->date('warranty_end')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('service_ref')->nullable();
            $table->unsignedBigInteger('installation_id')->nullable();
            $table->foreignId('site_id')->nullable()->constrained('inventory_sites')->nullOnDelete();
            $table->foreignId('tower_id')->nullable()->constrained('inventory_towers')->nullOnDelete();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('mounting')->nullable();
            $table->decimal('azimuth', 8, 2)->nullable();
            $table->string('sector')->nullable();
            $table->string('frequency')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['product_id', 'serial_number']);
            $table->index(['branch_id', 'status']);
            $table->index('location_id');
            $table->foreign('installation_id')->references('id')->on('installations')->nullOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
        });

        Schema::create('inventory_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->decimal('on_hand', 14, 3)->default(0);
            $table->decimal('reserved', 14, 3)->default(0);
            $table->decimal('in_transit', 14, 3)->default(0);
            $table->decimal('damaged', 14, 3)->default(0);
            $table->decimal('quarantine', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['location_id', 'product_id']);
            $table->index(['branch_id', 'product_id']);
        });

        Schema::create('inventory_stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('txn_number', 64)->unique();
            $table->string('type', 32);
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedBigInteger('installation_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->foreignId('site_id')->nullable()->constrained('inventory_sites')->nullOnDelete();
            $table->foreignId('tower_id')->nullable()->constrained('inventory_towers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // employee
            $table->foreignId('supplier_id')->nullable()->constrained('inventory_suppliers')->nullOnDelete();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->string('reason')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['branch_id', 'type', 'occurred_at']);
            $table->index(['product_id', 'occurred_at']);
            $table->foreign('installation_id')->references('id')->on('installations')->nullOnDelete();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->foreign('reversal_of_id')->references('id')->on('inventory_stock_transactions')->nullOnDelete();
        });

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('draft'); // draft|pending_approval|approved|partially_fulfilled|fulfilled|released|expired|cancelled
            $table->foreignId('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->unsignedBigInteger('installation_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->foreign('installation_id')->references('id')->on('installations')->nullOnDelete();
            $table->foreign('lead_id')->references('id')->on('crm_leads')->nullOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
        });

        Schema::create('inventory_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('inventory_reservations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->decimal('qty_requested', 14, 3);
            $table->decimal('qty_reserved', 14, 3)->default(0);
            $table->decimal('qty_fulfilled', 14, 3)->default(0);
            $table->decimal('qty_released', 14, 3)->default(0);
            $table->timestamps();
            $table->index('reservation_id');
        });

        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->string('status', 32)->default('draft'); // draft|submitted|approved|dispatched|in_transit|received|closed|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('has_discrepancy')->default(false);
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('inventory_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->decimal('qty_requested', 14, 3);
            $table->decimal('qty_dispatched', 14, 3)->default(0);
            $table->decimal('qty_received', 14, 3)->default(0);
            $table->text('discrepancy_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('draft'); // draft|submitted|approved|rejected|converted|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('inventory_purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('inventory_purchase_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->decimal('qty', 14, 3);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('inventory_suppliers')->restrictOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained('inventory_purchase_requests')->nullOnDelete();
            $table->string('status', 32)->default('draft'); // draft|submitted|approved|ordered|partially_received|received|closed|cancelled
            $table->date('expected_date')->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->string('zoho_bill_id')->nullable();
            $table->string('zoho_sync_status', 32)->default('pending');
            $table->string('zoho_idempotency_key')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('inventory_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('inventory_purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->decimal('qty_ordered', 14, 3);
            $table->decimal('qty_received', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('inventory_purchase_orders')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('inventory_suppliers')->nullOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->string('status', 32)->default('draft'); // draft|posted|cancelled
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('inventory_goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('inventory_goods_receipts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->string('serial_number')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_equipment_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('status', 32)->default('draft'); // draft|reserved|issued|completed|cancelled
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->string('zoho_invoice_id')->nullable();
            $table->string('zoho_sync_status', 32)->default('pending');
            $table->string('zoho_idempotency_key')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('inventory_equipment_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('inventory_equipment_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_customer_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('service_ref')->nullable();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->string('ownership_type', 32)->default('customer_owned'); // customer_owned|company_owned|leased
            $table->decimal('sale_price', 14, 2)->nullable();
            $table->decimal('install_fee', 14, 2)->nullable();
            $table->date('installed_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->unsignedBigInteger('installation_id')->nullable();
            $table->string('status', 32)->default('installed');
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->foreign('installation_id')->references('id')->on('installations')->nullOnDelete();
        });

        Schema::create('inventory_fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->string('name');
            $table->string('category', 64)->nullable();
            $table->foreignId('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('inventory_sites')->nullOnDelete();
            $table->foreignId('tower_id')->nullable()->constrained('inventory_towers')->nullOnDelete();
            $table->foreignId('custodian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('acquisition_cost', 14, 2)->nullable();
            $table->date('acquisition_date')->nullable();
            $table->string('condition', 32)->default('good');
            $table->string('status', 32)->default('active');
            $table->date('warranty_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('inventory_custody_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('custody_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->decimal('qty', 14, 3)->default(1);
            $table->string('status', 32)->default('issued'); // issued|returned|lost
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'user_id', 'status']);
        });

        Schema::create('inventory_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('equipment_id')->constrained('inventory_equipment')->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('repair_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('status', 32)->default('open'); // open|in_progress|completed|scrapped
            $table->string('fault_description')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->decimal('repair_cost', 14, 2)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('inventory_maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->foreignId('equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->foreignId('fixed_asset_id')->nullable()->constrained('inventory_fixed_assets')->nullOnDelete();
            $table->foreignId('tower_id')->nullable()->constrained('inventory_towers')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('inventory_sites')->nullOnDelete();
            $table->string('frequency', 32)->default('monthly'); // weekly|monthly|quarterly|yearly
            $table->date('next_due_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('last_task_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'is_active']);
            $table->foreign('last_task_id')->references('id')->on('tasks')->nullOnDelete();
        });

        Schema::create('inventory_stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('count_number', 64)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->string('status', 32)->default('draft'); // draft|in_progress|posted|cancelled
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('inventory_stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('inventory_stock_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->decimal('system_qty', 14, 3)->default(0);
            $table->decimal('counted_qty', 14, 3)->nullable();
            $table->decimal('variance_qty', 14, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->string('rule_type', 32)->default('low_stock'); // low_stock|expiry|overstock
            $table->decimal('threshold', 14, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = [
            'inventory_alert_rules',
            'inventory_stock_count_lines',
            'inventory_stock_counts',
            'inventory_maintenance_plans',
            'inventory_repairs',
            'inventory_custody_records',
            'inventory_fixed_assets',
            'inventory_customer_equipment',
            'inventory_equipment_sale_items',
            'inventory_equipment_sales',
            'inventory_goods_receipt_items',
            'inventory_goods_receipts',
            'inventory_purchase_order_items',
            'inventory_purchase_orders',
            'inventory_purchase_request_items',
            'inventory_purchase_requests',
            'inventory_transfer_items',
            'inventory_transfers',
            'inventory_reservation_items',
            'inventory_reservations',
            'inventory_stock_transactions',
            'inventory_stock_balances',
            'inventory_equipment',
            'inventory_reservation_sequences',
            'inventory_asset_sequences',
            'inventory_sale_sequences',
            'inventory_po_sequences',
            'inventory_transfer_sequences',
            'inventory_equipment_sequences',
            'inventory_txn_sequences',
            'inventory_suppliers',
            'inventory_towers',
            'inventory_sites',
            'inventory_locations',
            'inventory_products',
            'inventory_product_categories',
        ];
        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }
    }
};
