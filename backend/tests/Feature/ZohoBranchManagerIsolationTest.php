<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ZohoBranchManagerIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_branch_manager_only_sees_own_branch_customers_and_invoices(): void
    {
        $branchA = Branch::factory()->create(['code' => 'A']);
        $branchB = Branch::factory()->create(['code' => 'B']);

        $manager = User::factory()->create();
        $manager->assignRole(User::ROLE_BRANCH_MANAGER);
        $manager->branches()->attach($branchA->id);

        $customerA = Customer::withoutGlobalScopes()->create([
            'branch_id' => $branchA->id,
            'zoho_contact_id' => 'A-1',
            'contact_name' => 'Branch A Customer',
            'currency' => 'AFN',
            'status' => 'active',
            'is_unmapped' => false,
            'sync_status' => 'synced',
        ]);

        $customerB = Customer::withoutGlobalScopes()->create([
            'branch_id' => $branchB->id,
            'zoho_contact_id' => 'B-1',
            'contact_name' => 'Branch B Customer',
            'currency' => 'AFN',
            'status' => 'active',
            'is_unmapped' => false,
            'sync_status' => 'synced',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'branch_id' => $branchA->id,
            'customer_id' => $customerA->id,
            'zoho_invoice_id' => 'INV-A',
            'invoice_number' => 'A-1',
            'status' => 'unpaid',
            'currency' => 'AFN',
            'total' => '100.0000',
            'amount_paid' => '0',
            'balance' => '100.0000',
            'sync_status' => 'synced',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'branch_id' => $branchB->id,
            'customer_id' => $customerB->id,
            'zoho_invoice_id' => 'INV-B',
            'invoice_number' => 'B-1',
            'status' => 'unpaid',
            'currency' => 'AFN',
            'total' => '200.0000',
            'amount_paid' => '0',
            'balance' => '200.0000',
            'sync_status' => 'synced',
        ]);

        Sanctum::actingAs($manager);

        $customers = collect($this->getJson('/api/v1/customers')->assertOk()->json('data'));
        $this->assertTrue($customers->pluck('contact_name')->contains('Branch A Customer'));
        $this->assertFalse($customers->pluck('contact_name')->contains('Branch B Customer'));

        $invoices = collect($this->getJson('/api/v1/invoices')->assertOk()->json('data'));
        $this->assertTrue($invoices->pluck('invoice_number')->contains('A-1'));
        $this->assertFalse($invoices->pluck('invoice_number')->contains('B-1'));

        $this->getJson('/api/v1/customers/'.$customerB->id)->assertStatus(404);
    }
}
