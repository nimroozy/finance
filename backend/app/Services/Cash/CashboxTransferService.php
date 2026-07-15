<?php

namespace App\Services\Cash;

use App\Models\BranchCashbox;
use App\Models\BranchCashboxTransfer;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CashboxTransferService
{
    public function __construct(private BranchCashboxService $cashboxes) {}

    public function draft(User $actor, BranchCashbox $from, ?BranchCashbox $to, string $amount, string $type = 'cashbox_transfer', array $bank = []): BranchCashboxTransfer
    {
        if (!Money::isPositive($amount) || ($type === 'cashbox_transfer' && ! $to)) throw new InvalidArgumentException('Valid transfer amount and destination are required.');
        return BranchCashboxTransfer::create([
            'uuid' => (string) Str::uuid(), 'transfer_number' => 'CBT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5)),
            'from_cashbox_id' => $from->id, 'to_cashbox_id' => $to?->id, 'branch_id' => $from->branch_id,
            'type' => $type, 'currency' => $from->currency, 'amount' => Money::normalize($amount), 'status' => 'draft',
            'bank_name' => $bank['bank_name'] ?? null, 'bank_reference' => $bank['bank_reference'] ?? null, 'deposited_on' => $bank['deposited_on'] ?? null, 'notes' => $bank['notes'] ?? null, 'created_by' => $actor->id,
        ]);
    }

    public function submit(BranchCashboxTransfer $transfer, User $actor): BranchCashboxTransfer { return $this->change($transfer, 'draft', 'submitted', $actor, 'submitted_at'); }
    public function approve(BranchCashboxTransfer $transfer, User $actor): BranchCashboxTransfer { $t = $this->change($transfer, 'submitted', 'approved', $actor, 'approved_at'); $t->approved_by = $actor->id; $t->save(); return $t; }

    public function send(BranchCashboxTransfer $transfer, User $actor): BranchCashboxTransfer
    {
        return DB::transaction(function () use ($transfer, $actor) {
            $locked = BranchCashboxTransfer::withoutGlobalScopes()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'approved') throw new InvalidArgumentException('Only approved transfers can be sent.');
            $from = BranchCashbox::withoutGlobalScopes()->findOrFail($locked->from_cashbox_id);
            $this->cashboxes->debit($from, $locked->amount, 'cashbox_transfer_sent', $actor, $locked->transfer_number);
            $locked->status = 'sent'; $locked->sent_at = now(); $locked->save();
            return $locked;
        });
    }

    public function receive(BranchCashboxTransfer $transfer, User $actor): BranchCashboxTransfer
    {
        return DB::transaction(function () use ($transfer, $actor) {
            $locked = BranchCashboxTransfer::withoutGlobalScopes()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'sent') throw new InvalidArgumentException('Only sent transfers can be received.');
            if ($locked->type === 'bank_deposit') { $locked->status = 'received'; $locked->received_by = $actor->id; $locked->received_at = now(); $locked->save(); return $locked; }
            $to = BranchCashbox::withoutGlobalScopes()->findOrFail($locked->to_cashbox_id);
            $this->cashboxes->credit($to, $locked->amount, 'cashbox_transfer_received', null, $actor, $locked->transfer_number);
            $locked->status = 'received'; $locked->received_by = $actor->id; $locked->received_at = now(); $locked->save();
            return $locked;
        });
    }

    public function reverse(BranchCashboxTransfer $transfer, User $actor, string $reason): BranchCashboxTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $reason) {
            $locked = BranchCashboxTransfer::withoutGlobalScopes()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->status, ['approved', 'sent'], true)) throw new InvalidArgumentException('Only approved or sent transfers can be reversed.');
            if ($locked->status === 'sent') $this->cashboxes->credit(BranchCashbox::withoutGlobalScopes()->findOrFail($locked->from_cashbox_id), $locked->amount, 'cashbox_transfer_reversal', null, $actor, $reason);
            $locked->status = 'reversed'; $locked->reversed_at = now(); $locked->save();
            return $locked;
        });
    }

    private function change(BranchCashboxTransfer $transfer, string $from, string $to, User $actor, string $at): BranchCashboxTransfer
    {
        return DB::transaction(function () use ($transfer, $from, $to, $actor, $at) {
            $locked = BranchCashboxTransfer::withoutGlobalScopes()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== $from) throw new InvalidArgumentException("Transfer must be {$from}.");
            $locked->status = $to; $locked->{$at} = now(); $locked->save(); return $locked;
        });
    }
}
