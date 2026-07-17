<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage102CleanupDemoTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_dry_run_reports_candidates_without_deleting(): void
    {
        $branch = $this->makeBranch(['code' => 'CLN', 'name_en' => 'STAGE10-TEST Branch Cleanup']);
        $demo = $this->makeCustomer($branch, [
            'contact_name' => 'STAGE8-TEST Customer DO NOT USE',
            'zoho_contact_id' => 'ZOHO-DEMO-CLN',
        ]);
        $keep = $this->makeCustomer($branch, [
            'contact_name' => 'Real Production Customer',
            'zoho_contact_id' => 'ZOHO-KEEP-CLN',
        ]);

        $exit = Artisan::call('stage102:cleanup-demo', ['--dry-run' => true]);
        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $report = json_decode($output, true);
        $this->assertIsArray($report);
        $this->assertSame('dry-run', $report['mode']);
        $this->assertGreaterThanOrEqual(1, $report['candidate_count']);

        $ids = collect($report['candidates'])->where('table', 'customers')->pluck('id')->all();
        $this->assertContains($demo->id, $ids);
        $this->assertNotContains($keep->id, $ids);

        $this->assertNull($demo->fresh()->deleted_at);
        $this->assertSame(Customer::STATUS_ACTIVE, $demo->fresh()->status);
        $this->assertArrayHasKey('payments', $report['protected']);
        $this->assertArrayHasKey('stock_transactions', $report['protected']);
    }

    public function test_dry_run_never_touches_payments(): void
    {
        $branch = $this->makeBranch(['code' => 'PAY', 'name_en' => 'Normal Branch']);
        $customer = $this->makeCustomer($branch, [
            'contact_name' => 'STAGE9-TEST Payer',
            'zoho_contact_id' => 'ZOHO-PAY-1',
        ]);
        $paymentCount = Payment::query()->count();

        Artisan::call('stage102:cleanup-demo', ['--dry-run' => true]);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame($paymentCount, Payment::query()->count());
        $cand = collect($report['candidates'])->firstWhere('id', $customer->id);
        $this->assertNotNull($cand);
        $this->assertFalse($cand['will_touch_payments']);
        $this->assertFalse($cand['will_touch_stock_transactions']);
    }
}
