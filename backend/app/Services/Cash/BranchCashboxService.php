<?php

namespace App\Services\Cash;

use App\Models\BranchCashbox;
use App\Models\BranchCashboxTransaction;
use App\Models\CashHandoverRequest;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BranchCashboxService
{
    public function ensure(int $branchId, string $currency = 'AFN', ?User $actor = null): BranchCashbox
    {
        return BranchCashbox::withoutGlobalScopes()->firstOrCreate(
            ['branch_id' => $branchId, 'currency' => strtoupper($currency), 'name' => 'Main cashbox'],
            ['uuid' => (string) Str::uuid(), 'balance' => Money::normalize('0'), 'is_active' => true, 'created_by' => $actor?->id]
        );
    }

    public function credit(BranchCashbox $cashbox, string $amount, string $type, ?CashHandoverRequest $handover = null, ?User $actor = null, ?string $notes = null): BranchCashboxTransaction
    {
        return $this->post($cashbox, $amount, $type, $handover, $actor, $notes, false);
    }

    public function debit(BranchCashbox $cashbox, string $amount, string $type, ?User $actor = null, ?string $notes = null): BranchCashboxTransaction
    {
        return $this->post($cashbox, $amount, $type, null, $actor, $notes, true);
    }

    public function recalculate(BranchCashbox $cashbox): string
    {
        return Money::sum(BranchCashboxTransaction::withoutGlobalScopes()->where('cashbox_id', $cashbox->id)->pluck('amount'));
    }

    private function post(BranchCashbox $cashbox, string $amount, string $type, ?CashHandoverRequest $handover, ?User $actor, ?string $notes, bool $debit): BranchCashboxTransaction
    {
        return DB::transaction(function () use ($cashbox, $amount, $type, $handover, $actor, $notes, $debit) {
            $locked = BranchCashbox::withoutGlobalScopes()->whereKey($cashbox->id)->lockForUpdate()->firstOrFail();
            $amount = Money::normalize($amount);
            if (!Money::isPositive($amount)) throw new InvalidArgumentException('Cashbox amount must be positive.');
            $before = Money::normalize($locked->balance);
            $signed = $debit ? Money::mul($amount, '-1') : $amount;
            $after = Money::add($before, $signed);
            if (Money::isNegative($after)) throw new InvalidArgumentException('Insufficient cashbox balance.');
            $locked->balance = $after;
            $locked->save();

            return BranchCashboxTransaction::create([
                'cashbox_id' => $locked->id, 'branch_id' => $locked->branch_id, 'handover_id' => $handover?->id,
                'type' => $type, 'amount' => $signed, 'balance_before' => $before, 'balance_after' => $after,
                'currency' => $locked->currency, 'reference' => $handover?->handover_number, 'notes' => $notes,
                'created_by' => $actor?->id, 'created_at' => now(),
            ]);
        });
    }
}
