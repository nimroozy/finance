<?php

namespace App\Jobs;

use App\Services\Zoho\ZohoInvoiceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RefreshZohoInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  list<string>  $zohoInvoiceIds
     */
    public function __construct(
        public array $zohoInvoiceIds,
        public ?int $paymentId = null,
    ) {
        $this->onQueue('zoho');
    }

    public function handle(ZohoInvoiceSyncService $invoices): void
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $this->zohoInvoiceIds))));
        if ($ids === []) {
            return;
        }

        try {
            $invoices->refreshByZohoIds($ids);
        } catch (Throwable $e) {
            $invoices->markRefreshFailed($ids, $e->getMessage());
            throw $e;
        }
    }
}
