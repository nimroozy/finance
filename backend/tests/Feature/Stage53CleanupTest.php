<?php

namespace Tests\Feature;

use App\Models\BranchCustomerPrefix;
use App\Models\Customer;
use App\Models\CustomerBranchMappingConflict;
use App\Services\Mapping\CustomerNumberExtractionService;
use App\Services\Mapping\MappingConflictReviewService;
use App\Services\Mapping\UnmappedCustomerClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage53CleanupTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    protected function seedPrefixes(): array
    {
        $kabul = $this->makeBranch(['code' => 'KABUL', 'name_en' => 'Kabul', 'receipt_prefix' => 'KBL']);
        $nimruz = $this->makeBranch(['code' => '01', 'name_en' => 'Nimruz', 'receipt_prefix' => 'NMZ']);
        foreach ([['KBL', $kabul], ['NMZ', $nimruz]] as [$prefix, $branch]) {
            BranchCustomerPrefix::query()->create([
                'branch_id' => $branch->id,
                'prefix' => $prefix,
                'normalized_prefix' => $prefix,
                'active' => true,
                'priority' => 100,
            ]);
        }

        return compact('kabul', 'nimruz');
    }

    public function test_number_extraction_examples(): void
    {
        $this->seedPrefixes();
        $svc = app(CustomerNumberExtractionService::class);

        foreach ([
            ['KBL-1001 Ahmad Market', 'KBL-1001', 'Ahmad Market'],
            ['KBL1001 Ahmad Market', 'KBL-1001', 'Ahmad Market'],
            ['NMZ_2054 Company', 'NMZ-2054', 'Company'],
            ['NMZ 1054 Ahmadi Market', 'NMZ-1054', 'Ahmadi Market'],
        ] as [$name, $number, $clean]) {
            $out = $svc->extract(['customer_number' => null, 'contact_name' => $name]);
            $this->assertTrue($out['valid'], $name);
            $this->assertSame($number, $out['extracted_customer_number'], $name);
            $this->assertSame($clean, $out['clean_display_name'], $name);
        }

        $unknown = $svc->extract(['customer_number' => null, 'contact_name' => 'ABC-001 Shop']);
        $this->assertFalse($unknown['valid']);
        $this->assertSame('unknown_prefix', $unknown['result_class']);

        $mid = $svc->extract(['customer_number' => null, 'contact_name' => 'Shop KBL-1001']);
        $this->assertFalse($mid['valid']);
    }

    public function test_backfill_dry_run_and_apply_protects_existing(): void
    {
        $branches = $this->seedPrefixes();
        $missing = $this->makeCustomer($branches['kabul'], [
            'customer_number' => null,
            'contact_name' => 'NMZ-2054 Company',
        ]);
        $existing = $this->makeCustomer($branches['kabul'], [
            'customer_number' => 'KBL-9',
            'contact_name' => 'KBL-9 Keep',
        ]);

        Artisan::call('customers:backfill-customer-numbers', ['--only-missing' => true]);
        $this->assertNull($missing->fresh()->customer_number);

        Artisan::call('customers:backfill-customer-numbers', ['--apply' => true, '--only-missing' => true]);
        $this->assertSame('NMZ-2054', $missing->fresh()->customer_number);
        $this->assertSame('KBL-9', $existing->fresh()->customer_number);
    }

    public function test_conflict_review_requires_reason_and_resolves(): void
    {
        $branches = $this->seedPrefixes();
        $admin = $this->makeSuperAdmin();
        $customer = $this->makeCustomer($branches['kabul'], [
            'customer_number' => null,
            'contact_name' => 'NMZ-1045 Shop',
            'is_unmapped' => true,
        ]);
        $conflict = CustomerBranchMappingConflict::query()->create([
            'customer_id' => $customer->id,
            'conflict_type' => CustomerBranchMappingConflict::TYPE_PREFIX_LOCATION_MISMATCH,
            'status' => 'open',
            'prefix_branch_id' => $branches['nimruz']->id,
            'location_branch_id' => $branches['kabul']->id,
            'detected_prefix' => 'NMZ',
            'customer_number' => 'NMZ-1045',
            'explanation' => 'test',
        ]);

        $this->actingAsUser($admin);
        $this->postJson('/api/v1/customer-prefix-mappings/conflicts/'.$conflict->id.'/resolve', [
            'action' => MappingConflictReviewService::ACTION_APPLY_PREFIX,
            'branch_id' => $branches['nimruz']->id,
        ])->assertStatus(422);

        $this->postJson('/api/v1/customer-prefix-mappings/conflicts/'.$conflict->id.'/resolve', [
            'action' => MappingConflictReviewService::ACTION_APPLY_PREFIX,
            'branch_id' => $branches['nimruz']->id,
            'reason' => 'Confirmed Nimruz prefix ownership after review',
        ])->assertOk();

        $this->assertSame('resolved', $conflict->fresh()->status);
        $this->assertSame($branches['nimruz']->id, $customer->fresh()->branch_id);
    }

    public function test_headquarter_classification_and_unmapped_classifier(): void
    {
        $branches = $this->seedPrefixes();
        $hq = $this->makeBranch(['code' => 'HQ', 'name_en' => 'Headquarter', 'receipt_prefix' => 'HQ']);
        $hq->update(['is_headquarter' => true, 'exclude_from_field_collection' => true]);

        $admin = $this->makeSuperAdmin();
        $this->actingAsUser($admin);
        $customer = $this->makeCustomer($branches['kabul'], [
            'customer_number' => null,
            'contact_name' => 'Internal HQ desk',
            'is_unmapped' => true,
        ]);

        $this->postJson('/api/v1/customer-prefix-mappings/customers/'.$customer->id.'/classify', [
            'unmapped_classification' => UnmappedCustomerClassifier::CLASS_HEADQUARTER,
            'is_headquarter_owned' => true,
            'exclude_from_field_collection' => true,
            'reason' => 'Central HQ customer, not field collection',
        ])->assertOk();

        $fresh = $customer->fresh();
        $this->assertTrue($fresh->is_headquarter_owned);
        $this->assertTrue($fresh->exclude_from_field_collection);
        $this->assertFalse($fresh->is_unmapped);
    }

    protected function makeSuperAdmin()
    {
        $this->seedRoles();
        $user = \App\Models\User::factory()->create(['status' => 'active']);
        $user->assignRole(\App\Models\User::ROLE_SUPER_ADMIN);

        return $user;
    }
}
