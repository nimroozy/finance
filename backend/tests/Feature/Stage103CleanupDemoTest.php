<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage103CleanupDemoTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_dry_run_reads_manifest_without_mutating(): void
    {
        $branch = $this->makeBranch(['code' => 'M1', 'name_en' => 'Manifest Branch']);
        $demo = $this->makeCustomer($branch, [
            'contact_name' => 'STAGE8-TEST Manifest Customer',
            'zoho_contact_id' => 'ZOHO-M1',
        ]);

        $path = $this->writeTempManifest([
            [
                'table' => 'customers',
                'id' => $demo->id,
                'action' => 'archive',
                'classification' => 'archive_only',
                'label' => $demo->contact_name,
            ],
        ]);

        $exit = Artisan::call('stage103:cleanup-demo', [
            '--manifest' => $path,
            '--dry-run' => true,
        ]);
        $this->assertSame(0, $exit);
        $report = json_decode(Artisan::output(), true);
        $this->assertSame('dry-run', $report['mode']);
        $this->assertSame(1, $report['item_count']);
        $this->assertNull($demo->fresh()->deleted_at);
        $this->assertSame(Customer::STATUS_ACTIVE, $demo->fresh()->status);
    }

    public function test_apply_archives_payment_linked_customer_without_deleting_payments(): void
    {
        $branch = $this->makeBranch(['code' => 'M2', 'name_en' => 'Pay Branch']);
        $customer = $this->makeCustomer($branch, [
            'contact_name' => 'STAGE5 TEST CUSTOMER - DO NOT USE',
            'zoho_contact_id' => 'ZOHO-M2',
        ]);
        $payment = Payment::factory()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
        ]);
        $before = Payment::query()->count();

        $path = $this->writeTempManifest([
            [
                'table' => 'customers',
                'id' => $customer->id,
                'action' => 'archive',
                'classification' => 'preserve_financial',
                'label' => $customer->contact_name,
            ],
        ]);

        $exit = Artisan::call('stage103:cleanup-demo', [
            '--manifest' => $path,
            '--apply' => true,
        ]);
        $this->assertSame(0, $exit);
        $report = json_decode(Artisan::output(), true);
        $this->assertSame('apply', $report['mode']);
        $this->assertTrue($report['counts']['payments_unchanged']);
        $this->assertSame($before, Payment::query()->count());
        $this->assertNotNull(Payment::query()->find($payment->id));

        $fresh = Customer::withoutGlobalScopes()->withTrashed()->find($customer->id);
        $this->assertNotNull($fresh);
        $this->assertSame(Customer::STATUS_ARCHIVED, $fresh->status);
        $this->assertStringStartsWith('[ARCHIVED TEST]', (string) $fresh->contact_name);
        $this->assertNotNull($fresh->deleted_at);
    }

    public function test_apply_upgrades_delete_to_archive_when_customer_has_payments(): void
    {
        $branch = $this->makeBranch(['code' => 'M3', 'name_en' => 'Guard Branch']);
        $customer = $this->makeCustomer($branch, [
            'contact_name' => 'STAGE9-TEST Payer Guard',
            'zoho_contact_id' => 'ZOHO-M3',
        ]);
        Payment::factory()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
        ]);

        $path = $this->writeTempManifest([
            [
                'table' => 'customers',
                'id' => $customer->id,
                'action' => 'delete',
                'classification' => 'safe_to_delete',
            ],
        ]);

        Artisan::call('stage103:cleanup-demo', [
            '--manifest' => $path,
            '--apply' => true,
        ]);
        $report = json_decode(Artisan::output(), true);
        $result = $report['results'][0];
        $this->assertSame('archive_due_to_payments', $result['action_overridden'] ?? null);
        $this->assertSame('archived', $result['status']);
        $this->assertTrue($report['counts']['payments_unchanged']);
    }

    public function test_disable_rename_user(): void
    {
        $user = User::factory()->create([
            'name' => 'STAGE3-TEST Manager',
            'email' => 'stage3.manager@test.local',
            'status' => User::STATUS_ACTIVE,
        ]);

        $path = $this->writeTempManifest([
            [
                'table' => 'users',
                'id' => $user->id,
                'action' => 'disable_rename',
                'classification' => 'disable_rename',
            ],
        ]);

        Artisan::call('stage103:cleanup-demo', [
            '--manifest' => $path,
            '--apply' => true,
        ]);

        $fresh = $user->fresh();
        $this->assertSame(User::STATUS_DISABLED, $fresh->status);
        $this->assertStringStartsWith('[ARCHIVED TEST]', $fresh->name);
        $this->assertStringStartsWith('archived.', $fresh->email);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function writeTempManifest(array $items): string
    {
        $path = storage_path('app/stage103-test-manifest-'.uniqid('', true).'.json');
        File::put($path, json_encode([
            'version' => 1,
            'items' => $items,
        ], JSON_PRETTY_PRINT));

        return $path;
    }
}
