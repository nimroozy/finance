<?php

namespace App\Services\Cash;

use App\Models\CashHandoverRequest;
use App\Models\CashHandoverStatusHistory;
use App\Models\User;
use InvalidArgumentException;

class CashHandoverStatusTransitionService
{
    private const ALLOWED = [
        'draft' => ['submitted'],
        'submitted' => ['clarification', 'approved', 'partially_approved', 'rejected'],
        'clarification' => ['submitted', 'rejected'],
    ];

    public function transition(CashHandoverRequest $handover, string $to, ?User $actor = null, ?string $notes = null): CashHandoverRequest
    {
        $from = $handover->status;
        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new InvalidArgumentException("Invalid cash handover transition from {$from} to {$to}.");
        }
        $handover->status = $to;
        $handover->save();
        CashHandoverStatusHistory::create(['handover_id' => $handover->id, 'from_status' => $from, 'to_status' => $to, 'changed_by' => $actor?->id, 'notes' => $notes, 'created_at' => now()]);
        return $handover;
    }
}
