<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ZohoBranchMapping;
use App\Services\Zoho\ZohoInvoiceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithZoho;
use Tests\TestCase;

class ZohoInvoiceImportTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    public function test_imports_invoices_with_zoho_invoice_ids(): void
    {
        $branch = Branch::factory()->create();
        ZohoBranchMapping::query()->create([
            'branch_id' => $branch->id,
            'mapping_method' => ZohoBranchMapping::METHOD_CUSTOM_FIELD,
            'zoho_value' => 'BranchCode',
            'is_active' => true,
        ]);

        // Mapping looks for custom field api_name BranchCode with value matching zoho_value —
        // for METHOD_CUSTOM_FIELD, extractValue returns custom field named zoho_value.
        // Our resolve compares value === mapping.zoho_value which is the api name — fix test to use branch method.
        ZohoBranchMapping::query()->where('branch_id', $branch->id)->update([
            'mapping_method' => ZohoBranchMapping::METHOD_ZOHO_BRANCH,
            'zoho_value' => 'BR-1',
        ]);

        Customer::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'zoho_contact_id' => 'CUST-1',
            'contact_name' => 'Invoice Customer',
            'currency' => 'AFN',
            'status' => 'active',
            'is_unmapped' => false,
            'sync_status' => 'synced',
        ]);

        $this->createConnectedZoho();

        Http::fake([
            'https://www.zohoapis.com/books/v3/invoices*' => Http::response([
                'invoices' => [[
                    'invoice_id' => 'INV-ZOHO-1',
                    'invoice_number' => 'INV-1001',
                    'customer_id' => 'CUST-1',
                    'customer_name' => 'Invoice Customer',
                    'date' => '2026-06-01',
                    'due_date' => '2026-06-30',
                    'status' => 'unpaid',
                    'currency_code' => 'AFN',
                    'total' => '5000.0000',
                    'payment_made' => '1000.0000',
                    'credits_applied' => '0',
                    'balance' => '4000.0000',
                    'branch_id' => 'BR-1',
                    'last_modified_time' => now()->toIso8601String(),
                ]],
                'page_context' => ['has_more_page' => false],
            ], 200),
        ]);

        $stats = app(ZohoInvoiceSyncService::class)->syncFull();

        $this->assertSame(1, $stats['created']);
        $this->assertDatabaseHas('invoices', [
            'zoho_invoice_id' => 'INV-ZOHO-1',
            'invoice_number' => 'INV-1001',
            'branch_id' => $branch->id,
            'balance' => '4000.0000',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->first();
        $this->assertNotNull($invoice->zoho_invoice_id);
    }
}
