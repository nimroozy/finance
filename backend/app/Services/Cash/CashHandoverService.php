<?php

namespace App\Services\Cash;

use App\Events\BusinessNotificationRequested;
use App\Models\Branch;
use App\Models\CashHandoverItem;
use App\Models\CashHandoverRequest;
use App\Models\CashHandoverReview;
use App\Models\CashHandoverVariance;
use App\Models\CashHandoverReceipt;
use App\Models\CashHandoverSequence;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Wallets\CollectorWalletService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CashHandoverService
{
    public function __construct(
        private CashHandoverStatusTransitionService $transitions,
        private CollectorWalletService $wallets,
        private BranchCashboxService $cashboxes,
        private AuditLogger $audit,
    ) {}

    /** @return \Illuminate\Support\Collection<int, Payment> */
    public function eligible(int $collectorId, int $branchId, string $currency): \Illuminate\Support\Collection
    {
        return $this->eligibleQuery($collectorId, $branchId, $currency)->get();
    }

    private function eligibleQuery(int $collectorId, int $branchId, string $currency): \Illuminate\Database\Eloquent\Builder
    {
        return Payment::withoutGlobalScopes()->with('method')->where([
            'collector_id' => $collectorId, 'branch_id' => $branchId, 'currency' => strtoupper($currency),
            'handover_status' => 'pending_handover',
        ])->where('status', Payment::STATUS_SETTLED_PENDING_HANDOVER)
            ->whereHas('method', fn ($q) => $q->where('affects_cash_wallet', true))
            ->whereDoesntHave('reversals', fn ($q) => $q->where('status', 'approved'))
            ->whereNotIn('id', CashHandoverItem::query()->where('is_active', true)->select('payment_id'));
    }

    public function draft(User $actor, int $collectorId, int $branchId, string $currency, array $paymentIds, string $declaredAmount, ?string $notes = null): CashHandoverRequest
    {
        return DB::transaction(function () use ($actor, $collectorId, $branchId, $currency, $paymentIds, $declaredAmount, $notes) {
            $payments = $this->lockedEligible($collectorId, $branchId, $currency, $paymentIds);
            $total = Money::sum($payments->pluck('amount'));
            if (!Money::isPositive($total)) throw new InvalidArgumentException('Select at least one eligible cash payment.');
            $cashbox = $this->cashboxes->ensure($branchId, $currency, $actor);
            $handover = CashHandoverRequest::create([
                'uuid' => (string) Str::uuid(), 'collector_id' => $collectorId, 'branch_id' => $branchId,
                'currency' => strtoupper($currency), 'declared_amount' => Money::normalize($declaredAmount),
                'expected_wallet_amount' => $total, 'selected_payment_total' => $total,
                'variance_amount' => Money::sub($declaredAmount, $total), 'status' => CashHandoverRequest::STATUS_DRAFT,
                'notes' => $notes, 'cashbox_id' => $cashbox->id, 'created_by' => $actor->id,
            ]);
            foreach ($payments as $payment) CashHandoverItem::create(['handover_id' => $handover->id, 'payment_id' => $payment->id, 'payment_amount' => $payment->amount, 'item_status' => 'selected']);
            $this->audit->log('cash_handover.drafted', $handover, null, [
                'payment_ids' => $paymentIds,
                'declared_amount' => Money::normalize($declaredAmount),
            ], $branchId);

            return $handover->load('items.payment');
        });
    }

    public function submit(CashHandoverRequest $handover, User $actor): CashHandoverRequest
    {
        return DB::transaction(function () use ($handover, $actor) {
            $locked = CashHandoverRequest::withoutGlobalScopes()->whereKey($handover->id)->lockForUpdate()->firstOrFail();
            $total = Money::sum($locked->items()->where('is_active', true)->pluck('payment_amount'));
            if (!Money::isPositive($total)) throw new InvalidArgumentException('Handover has no active items.');
            $locked->selected_payment_total = $total;
            $locked->expected_wallet_amount = $total;
            $locked->submitted_by = $actor->id; $locked->submitted_at = now(); $locked->save();
            $updated = $this->transitions->transition($locked, CashHandoverRequest::STATUS_SUBMITTED, $actor);
            $this->audit->log('cash_handover.submitted', $updated, null, ['status' => $updated->status], $updated->branch_id);
            $this->notifyHandover('cash_handover_submitted', $updated, $actor);

            return $updated;
        });
    }

    public function approve(CashHandoverRequest $handover, User $actor, string $countedAmount, ?string $approvedAmount = null, ?string $notes = null): CashHandoverRequest
    {
        return DB::transaction(function () use ($handover, $actor, $countedAmount, $approvedAmount, $notes) {
            $locked = CashHandoverRequest::withoutGlobalScopes()->with('items')->whereKey($handover->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CashHandoverRequest::STATUS_SUBMITTED) throw new InvalidArgumentException('Only submitted handovers can be approved.');
            $selected = Money::sum($locked->items->where('is_active', true)->pluck('payment_amount'));
            $approved = Money::normalize($approvedAmount ?? $countedAmount);
            if (!Money::isPositive($approved) || Money::compare($approved, $selected) === 1) throw new InvalidArgumentException('Approved amount must be positive and cannot exceed selected payments.');
            $cashbox = $this->cashboxes->ensure($locked->branch_id, $locked->currency, $actor);
            $locked->cashbox_id = $cashbox->id; $locked->counted_amount = Money::normalize($countedAmount); $locked->approved_amount = $approved;
            $locked->variance_amount = Money::sub($approved, $selected);
            $locked->variance_type = Money::isZero($locked->variance_amount) ? null : (Money::isNegative($locked->variance_amount) ? 'short' : 'over');
            $locked->reviewed_by = $actor->id; $locked->approved_by = $actor->id; $locked->reviewed_at = now(); $locked->approved_at = now(); $locked->save();
            $status = Money::equals($approved, $selected) ? CashHandoverRequest::STATUS_APPROVED : CashHandoverRequest::STATUS_PARTIALLY_APPROVED;
            $this->transitions->transition($locked, $status, $actor, $notes);
            $this->wallets->debitForCashHandover($locked, $approved, $actor);
            $this->cashboxes->credit($cashbox, $approved, 'cash_handover_credit', $locked, $actor, $notes);
            $remaining = $approved;
            foreach ($locked->items->where('is_active', true) as $item) {
                $itemAmount = Money::min($item->payment_amount, $remaining);
                if (Money::isPositive($itemAmount)) {
                    $fully = Money::equals($itemAmount, $item->payment_amount);
                    $item->approved_amount = $itemAmount;
                    $item->item_status = $fully ? 'approved' : 'partial';
                    $item->save();
                    Payment::withoutGlobalScopes()->whereKey($item->payment_id)->update([
                        'handover_status' => $fully ? 'handed_over' : 'partially_handed_over',
                        'cash_handover_request_id' => $locked->id,
                    ]);
                    $remaining = Money::sub($remaining, $itemAmount);
                } else {
                    $item->item_status = 'not_approved';
                    $item->is_active = false;
                    $item->save();
                }
            }
            if (!Money::isZero($locked->variance_amount)) CashHandoverVariance::create(['handover_id' => $locked->id, 'expected_amount' => $selected, 'actual_amount' => $approved, 'variance_amount' => $locked->variance_amount, 'variance_type' => $locked->variance_type, 'notes' => $notes]);
            CashHandoverReview::create(['handover_id' => $locked->id, 'reviewer_id' => $actor->id, 'decision' => $status, 'counted_amount' => $countedAmount, 'approved_amount' => $approved, 'notes' => $notes]);
            $this->issueReceipt($locked, $actor);
            $fresh = $locked->fresh(['items.payment']);
            $this->audit->log('cash_handover.approved', $fresh, null, [
                'approved_amount' => $approved,
                'status' => $status,
                'handover_number' => $fresh?->handover_number,
                'zoho_customer_payment_created' => false,
            ], $locked->branch_id);
            $this->audit->log('cash_handover.completed_without_zoho_payment', $fresh, null, [
                'note' => 'Handover is internal custody only; Zoho customer payment already posted at collection time.',
                'payment_ids' => $fresh?->items?->pluck('payment_id')->filter()->values()->all(),
            ], $locked->branch_id);
            $this->notifyHandover('cash_handover_approved', $fresh, $actor);

            return $fresh;
        });
    }

    public function reject(CashHandoverRequest $handover, User $actor, string $notes): CashHandoverRequest
    {
        return DB::transaction(function () use ($handover, $actor, $notes) {
            $locked = CashHandoverRequest::withoutGlobalScopes()->whereKey($handover->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CashHandoverRequest::STATUS_SUBMITTED) throw new InvalidArgumentException('Only submitted handovers can be rejected.');
            $locked->rejected_at = now(); $locked->reviewed_by = $actor->id; $locked->reviewed_at = now(); $locked->review_notes = $notes; $locked->save();
            $locked->items()->update(['is_active' => false, 'item_status' => 'rejected']);
            $updated = $this->transitions->transition($locked, CashHandoverRequest::STATUS_REJECTED, $actor, $notes);
            $this->audit->log('cash_handover.rejected', $updated, null, ['notes' => $notes], $updated->branch_id);
            $this->notifyHandover('cash_handover_rejected', $updated, $actor);

            return $updated;
        });
    }

    private function lockedEligible(int $collectorId, int $branchId, string $currency, array $paymentIds): \Illuminate\Support\Collection
    {
        $ids = array_values(array_unique(array_map('intval', $paymentIds)));
        if ($ids === []) throw new InvalidArgumentException('Payment IDs are required.');
        $payments = $this->eligibleQuery($collectorId, $branchId, $currency)->whereIn('id', $ids)->lockForUpdate()->get();
        if ($payments->count() !== count($ids)) throw new InvalidArgumentException('One or more payments are not eligible for handover.');
        return $payments;
    }

    private function notifyHandover(string $event, CashHandoverRequest $handover, User $actor): void
    {
        BusinessNotificationRequested::dispatch($event, [
            'branch_id' => $handover->branch_id,
            'user_id' => $actor->id,
            'phone' => $actor->phone,
            'title' => str_replace('_', ' ', ucfirst($event)),
            'handover_id' => $handover->id,
            'status' => $handover->status,
        ]);
    }

    private function issueReceipt(CashHandoverRequest $handover, User $actor): void
    {
        $year = (int) now()->format('Y');
        $sequence = CashHandoverSequence::query()->firstOrCreate(['branch_id' => $handover->branch_id, 'year' => $year], ['next_number' => 1]);
        $sequence = CashHandoverSequence::query()->whereKey($sequence->id)->lockForUpdate()->firstOrFail();
        $number = $sequence->next_number; $sequence->increment('next_number');
        $branch = Branch::withoutGlobalScopes()->find($handover->branch_id);
        $prefix = $branch?->code ?: (string) $handover->branch_id;
        $receipt = sprintf('%s-HO-%d-%06d', $prefix, $year, $number);
        $handover->handover_number = $receipt; $handover->save();
        CashHandoverReceipt::create(['handover_id' => $handover->id, 'receipt_number' => $receipt, 'amount' => $handover->approved_amount, 'currency' => $handover->currency, 'issued_by' => $actor->id, 'issued_at' => now()]);
    }
}
