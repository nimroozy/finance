<?php

namespace App\Jobs;

use App\Models\Receipt;
use App\Services\Receipts\ReceiptService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateReceiptPdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $receiptId) {}

    public function handle(ReceiptService $receipts): void
    {
        $receipt = Receipt::withoutGlobalScopes()->find($this->receiptId);
        if (! $receipt) {
            return;
        }

        try {
            $receipts->generatePdf($receipt);
        } catch (Throwable $e) {
            Log::warning('GenerateReceiptPdfJob failed', [
                'receipt_id' => $this->receiptId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
