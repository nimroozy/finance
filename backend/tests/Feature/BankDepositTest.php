<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\Cash\BranchCashboxService;
use App\Services\Cash\CashboxTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_deposit_full_flow_and_reversal(): void
    {
        $actor = User::factory()->create();
        $branch = Branch::factory()->create();
        $cashboxes = app(BranchCashboxService::class);
        $cashbox = $cashboxes->ensure($branch->id, 'AFN', $actor);
        $cashboxes->credit($cashbox, '100.0000', 'opening', null, $actor);

        $service = app(CashboxTransferService::class);
        $deposit = $service->draft($actor, $cashbox, null, '25.0000', 'bank_deposit', [
            'bank_name' => 'Test Bank',
            'bank_reference' => 'DEP-1',
            'deposited_on' => today()->toDateString(),
        ]);
        $service->submit($deposit, $actor);
        $service->approve($deposit, $actor);
        $service->send($deposit, $actor);
        $this->assertSame('received', $service->receive($deposit, $actor)->status);
        $this->assertSame('75.0000', $cashbox->fresh()->balance);

        $this->assertSame('reversed', $service->reverse($deposit, $actor, 'Bank rejected deposit')->status);
        $this->assertSame('100.0000', $cashbox->fresh()->balance);
        $this->assertDatabaseHas('branch_cashbox_transfer_reversals', ['transfer_id' => $deposit->id]);
    }
}
