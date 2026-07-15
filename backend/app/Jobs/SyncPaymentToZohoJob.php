<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Zoho\ZohoPaymentSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPaymentToZohoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $paymentId) {}

    public function handle(ZohoPaymentSyncService $sync): void
    {
        $payment = Payment::withoutGlobalScopes()->find($this->paymentId);
        if (! $payment || $payment->isReversed() || ! $payment->isConfirmed()) {
            return;
        }

        try {
            $sync->sync($payment);
        } catch (Throwable $e) {
            Log::warning('SyncPaymentToZohoJob failed', [
                'payment_id' => $this->paymentId,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                return;
            }

            throw $e;
        }
    }
}
