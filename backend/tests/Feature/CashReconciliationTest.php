<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\Cash\BranchCashboxService;
use App\Services\Cash\CashReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_uses_cashbox_ledger(): void
    {
        $actor = User::factory()->create();
        $branch = Branch::factory()->create();
        $cashboxes = app(BranchCashboxService::class);
        $cashbox = $cashboxes->ensure($branch->id, 'AFN', $actor);
        $cashboxes->credit($cashbox, '100.0000', 'opening', null, $actor);
        $cashboxes->debit($cashbox, '30.0000', 'expense', $actor);

        $run = app(CashReconciliationService::class)->run($cashbox->fresh(), today()->toDateString(), $actor, '70.0000');

        $this->assertSame('completed', $run->status);
        $this->assertSame('100.0000', $run->credits);
        $this->assertSame('-30.0000', $run->debits);
        $this->assertSame('70.0000', $run->expected_balance);
        $this->assertSame('0.0000', $run->variance_amount);
    }
}
