<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentStatusHistory;
use App\Models\User;
use InvalidArgumentException;

class PaymentStatusTransitionService
{
    /**
     * @var array<string, list<string>>
     */
    public const ALLOWED = [
        Payment::STATUS_DRAFT => [
            Payment::STATUS_CONFIRMED_LOCAL,
            Payment::STATUS_PENDING_ZOHO_SYNC,
            Payment::STATUS_SETTLED_PENDING_HANDOVER,
            Payment::STATUS_EXPIRED,
        ],
        Payment::STATUS_CONFIRMED_LOCAL => [
            Payment::STATUS_PENDING_ZOHO_SYNC,
            Payment::STATUS_SETTLED_PENDING_HANDOVER,
            Payment::STATUS_SYNCED,
            Payment::STATUS_SYNC_FAILED,
            Payment::STATUS_REVERSED,
        ],
        Payment::STATUS_PENDING_ZOHO_SYNC => [
            Payment::STATUS_SYNCED,
            Payment::STATUS_SYNC_FAILED,
            Payment::STATUS_SETTLED_PENDING_HANDOVER,
            Payment::STATUS_REVERSED,
        ],
        Payment::STATUS_SETTLED_PENDING_HANDOVER => [
            Payment::STATUS_SYNCED,
            Payment::STATUS_SYNC_FAILED,
            Payment::STATUS_PENDING_ZOHO_SYNC,
            Payment::STATUS_REVERSED,
        ],
        Payment::STATUS_SYNC_FAILED => [
            Payment::STATUS_PENDING_ZOHO_SYNC,
            Payment::STATUS_SYNCED,
            Payment::STATUS_REVERSED,
        ],
        Payment::STATUS_SYNCED => [
            Payment::STATUS_REVERSED,
        ],
        Payment::STATUS_REVERSED => [],
        Payment::STATUS_EXPIRED => [],
    ];

    public function transition(
        Payment $payment,
        string $toStatus,
        ?User $actor = null,
        ?string $reason = null,
        ?array $meta = null,
    ): Payment {
        $from = $payment->status;
        $allowed = self::ALLOWED[$from] ?? [];

        if ($from === $toStatus) {
            return $payment;
        }

        if (! in_array($toStatus, $allowed, true)) {
            throw new InvalidArgumentException("Invalid payment status transition [{$from} → {$toStatus}].");
        }

        $payment->status = $toStatus;
        $payment->save();

        PaymentStatusHistory::create([
            'payment_id' => $payment->id,
            'from_status' => $from,
            'to_status' => $toStatus,
            'changed_by' => $actor?->id,
            'reason' => $reason,
            'meta' => $meta,
            'created_at' => now(),
        ]);

        return $payment->fresh();
    }
}
