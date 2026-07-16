<?php

namespace App\Services\Promises;

use App\Models\PromiseToPay;
use App\Models\PromiseToPayStatusHistory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromiseToPayService
{
    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): PromiseToPay
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => ['Promise amount must be greater than zero.']]);
        }

        $promisedDate = $data['promised_date'] ?? null;
        if (! $promisedDate) {
            throw ValidationException::withMessages(['promised_date' => ['Promised date is required.']]);
        }

        $allowPast = (bool) ($data['allow_past_date'] ?? false);
        if (! $allowPast && now()->startOfDay()->gt(\Carbon\Carbon::parse($promisedDate)->startOfDay())) {
            if (! $actor->isBranchManager() && ! $actor->isSuperAdmin() && ! $actor->isCentralFinanceAdmin()) {
                throw ValidationException::withMessages(['promised_date' => ['Promise date cannot be in the past.']]);
            }
        }

        $promise = PromiseToPay::create([
            'branch_id' => $data['branch_id'],
            'customer_id' => $data['customer_id'],
            'assignment_id' => $data['assignment_id'] ?? null,
            'visit_id' => $data['visit_id'] ?? null,
            'collector_id' => $data['collector_id'] ?? $actor->collector?->id,
            'created_by' => $actor->id,
            'amount' => number_format($amount, 4, '.', ''),
            'currency' => $data['currency'] ?? 'AFN',
            'promised_date' => $promisedDate,
            'status' => PromiseToPay::STATUS_ACTIVE,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->recordStatus($promise, null, PromiseToPay::STATUS_ACTIVE, $actor, 'created');
        $this->audit->log('promise.created', $promise, null, $promise->toArray(), $promise->branch_id);

        return $promise->fresh();
    }

    public function cancel(PromiseToPay $promise, User $actor, ?string $reason = null): PromiseToPay
    {
        if (! $promise->isOpen()) {
            throw ValidationException::withMessages(['promise' => ['Promise is not open.']]);
        }

        $from = $promise->status;
        $promise->update([
            'status' => PromiseToPay::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
            'cancel_reason' => $reason,
        ]);

        $this->recordStatus($promise, $from, PromiseToPay::STATUS_CANCELLED, $actor, $reason);
        $this->audit->log('promise.cancelled', $promise, null, $promise->toArray(), $promise->branch_id, $reason);

        return $promise->fresh();
    }

    public function fulfill(PromiseToPay $promise, User $actor): PromiseToPay
    {
        if (! $promise->isOpen()) {
            throw ValidationException::withMessages(['promise' => ['Promise is not open.']]);
        }

        $from = $promise->status;
        $promise->update([
            'status' => PromiseToPay::STATUS_FULFILLED,
            'fulfilled_at' => now(),
            'fulfilled_by' => $actor->id,
        ]);

        $this->recordStatus($promise, $from, PromiseToPay::STATUS_FULFILLED, $actor, 'fulfilled');
        $this->audit->log('promise.fulfilled', $promise, null, $promise->toArray(), $promise->branch_id);

        return $promise->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function supersede(PromiseToPay $promise, array $data, User $actor): PromiseToPay
    {
        return DB::transaction(function () use ($promise, $data, $actor) {
            if (! $promise->isOpen()) {
                throw ValidationException::withMessages(['promise' => ['Promise is not open.']]);
            }

            $data['branch_id'] = $promise->branch_id;
            $data['customer_id'] = $promise->customer_id;
            $data['assignment_id'] = $promise->assignment_id;
            $data['collector_id'] = $promise->collector_id;
            $new = $this->create($data, $actor);

            $from = $promise->status;
            $promise->update([
                'status' => PromiseToPay::STATUS_SUPERSEDED,
                'superseded_by_id' => $new->id,
            ]);

            $this->recordStatus($promise, $from, PromiseToPay::STATUS_SUPERSEDED, $actor, 'superseded');
            $this->audit->log('promise.superseded', $promise, null, $promise->toArray(), $promise->branch_id);

            return $new;
        });
    }

    public function updateStatusesByDate(?\DateTimeInterface $asOf = null): int
    {
        $asOf = \Carbon\Carbon::parse($asOf ?? now())->startOfDay();
        $dueSoonDays = (int) config('collection.promise.due_soon_days', 3);
        $updated = 0;

        $promises = PromiseToPay::withoutGlobalScopes()
            ->whereIn('status', PromiseToPay::OPEN_STATUSES)
            ->get();

        foreach ($promises as $promise) {
            $date = $promise->promised_date->copy()->startOfDay();
            $target = PromiseToPay::STATUS_ACTIVE;

            if ($date->lt($asOf)) {
                $target = PromiseToPay::STATUS_OVERDUE;
            } elseif ($date->equalTo($asOf)) {
                $target = PromiseToPay::STATUS_DUE_TODAY;
            } elseif ($date->lte($asOf->copy()->addDays($dueSoonDays))) {
                $target = PromiseToPay::STATUS_DUE_SOON;
            }

            if ($promise->status !== $target) {
                $from = $promise->status;
                $promise->update(['status' => $target]);
                $this->recordStatus($promise, $from, $target, null, 'scheduled_status_update');
                $updated++;
            }
        }

        return $updated;
    }

    protected function recordStatus(
        PromiseToPay $promise,
        ?string $from,
        string $to,
        ?User $actor,
        ?string $reason = null,
    ): void {
        PromiseToPayStatusHistory::create([
            'promise_id' => $promise->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor?->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
