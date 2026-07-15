<?php

namespace App\Services\Cash;

use App\Models\BranchCashbox;
use App\Models\BranchCashboxTransaction;
use App\Models\CashReconciliationRun;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;

class CashReconciliationService
{
    public function run(BranchCashbox $cashbox, string $date, User $actor, ?string $counted = null): CashReconciliationRun
    {
        $transactions = BranchCashboxTransaction::withoutGlobalScopes()->where('cashbox_id', $cashbox->id)->whereDate('created_at', $date)->get();
        $credits = Money::sum($transactions->filter(fn ($t) => ! Money::isNegative($t->amount))->pluck('amount'));
        $debits = Money::sum($transactions->filter(fn ($t) => Money::isNegative($t->amount))->pluck('amount'));
        $opening = Money::sub($cashbox->balance, Money::add($credits, $debits));
        $expected = Money::add($opening, Money::add($credits, $debits));
        return CashReconciliationRun::updateOrCreate(['cashbox_id' => $cashbox->id, 'reconciliation_date' => $date], ['uuid' => (string) Str::uuid(), 'branch_id' => $cashbox->branch_id, 'currency' => $cashbox->currency, 'opening_balance' => $opening, 'credits' => $credits, 'debits' => $debits, 'expected_balance' => $expected, 'counted_balance' => $counted, 'variance_amount' => $counted === null ? '0' : Money::sub($counted, $expected), 'status' => $counted === null ? 'draft' : 'completed', 'run_by' => $actor->id]);
    }
}
