<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\Cash\BranchCashboxService;
use App\Services\Cash\CashboxTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashboxTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashbox_transfer_full_flow_records_approval(): void
    {
        $actor = User::factory()->create();
        $fromBranch = Branch::factory()->create();
        $toBranch = Branch::factory()->create();
        $cashboxes = app(BranchCashboxService::class);
        $from = $cashboxes->ensure($fromBranch->id, 'AFN', $actor);
        $to = $cashboxes->ensure($toBranch->id, 'AFN', $actor);
        $cashboxes->credit($from, '100.0000', 'opening', null, $actor);

        $service = app(CashboxTransferService::class);
        $transfer = $service->draft($actor, $from, $to, '40.0000');
        $this->assertSame('submitted', $service->submit($transfer, $actor)->status);
        $this->assertSame('approved', $service->approve($transfer, $actor)->status);
        $this->assertDatabaseHas('branch_cashbox_transfer_approvals', ['transfer_id' => $transfer->id, 'decision' => 'approved']);
        $this->assertSame('sent', $service->send($transfer, $actor)->status);
        $this->assertSame('received', $service->receive($transfer, $actor)->status);
        $this->assertSame('60.0000', $from->fresh()->balance);
        $this->assertSame('40.0000', $to->fresh()->balance);
    }
}
