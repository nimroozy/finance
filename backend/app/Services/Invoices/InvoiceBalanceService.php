<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Services\Payments\AllocationService;
use App\Support\Money;
use Illuminate\Support\Collection;

class InvoiceBalanceService
{
    public function __construct(private AllocationService $allocations) {}

    /**
     * @return array{
     *   zoho_synced_balance:string,
     *   local_pending_allocation:string,
     *   effective_balance:string,
     *   balance_last_synced_at:?string,
     *   awaiting_zoho_refresh:bool,
     *   balance_sync_status:string,
     *   balance_sync_warning:?string
     * }
     */
    public function describe(Invoice $invoice): array
    {
        $zoho = Money::normalize((string) ($invoice->balance ?? '0'));
        $pending = $this->allocations->reservedLocalAmount((int) $invoice->id);
        $effective = $this->allocations->effectiveAvailableBalance($invoice);
        $awaiting = Money::compare($zoho, $effective) === 1
            && Money::isPositive($pending);

        $status = (string) ($invoice->balance_sync_status ?? $invoice->sync_status ?? 'synced');
        if ($awaiting && $status === 'synced') {
            $status = 'awaiting_refresh';
        }

        return [
            'zoho_synced_balance' => $zoho,
            'local_pending_allocation' => $pending,
            'effective_balance' => $effective,
            'balance_last_synced_at' => optional($invoice->last_synced_at)?->toIso8601String(),
            'awaiting_zoho_refresh' => $awaiting || $status === 'awaiting_refresh' || $status === 'refresh_failed',
            'balance_sync_status' => $status,
            'balance_sync_warning' => $invoice->balance_sync_warning,
        ];
    }

    /**
     * @param  Collection<int, Invoice>|iterable<Invoice>  $invoices
     * @return list<array<string, mixed>>
     */
    public function decorateMany(iterable $invoices): array
    {
        $out = [];
        foreach ($invoices as $invoice) {
            $out[] = array_merge($invoice->toArray(), $this->describe($invoice));
        }

        return $out;
    }

    public function decorateOne(Invoice $invoice): array
    {
        return array_merge($invoice->toArray(), $this->describe($invoice));
    }
}
