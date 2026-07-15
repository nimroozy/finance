<?php

namespace App\Services\Cash;

use App\Models\CustodyConflict;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\User;

class CustodyAwareReversalService
{
    public function requiresCustodyReview(Payment $payment): bool
    {
        return $payment->handover_status === 'handed_over';
    }

    public function createConflict(Payment $payment, PaymentReversal $reversal, User $actor, string $reason): CustodyConflict
    {
        return CustodyConflict::withoutGlobalScopes()->updateOrCreate(
            ['payment_id' => $payment->id],
            ['handover_id' => $payment->cash_handover_request_id, 'reversal_id' => $reversal->id, 'branch_id' => $payment->branch_id, 'status' => 'pending_review', 'reason' => $reason, 'requested_by' => $actor->id]
        );
    }
}
