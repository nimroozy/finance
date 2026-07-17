<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Services\Service;

/**
 * READ-ONLY billing view from Zoho-synced Customer / Invoice / Payment tables.
 * Never invents balances or writes payment/wallet data.
 */
class ServiceBillingViewService
{
    /**
     * @return array<string, mixed>
     */
    public function forService(Service $service): array
    {
        $customer = Customer::withoutGlobalScopes()->find($service->customer_id);

        $invoices = Invoice::withoutGlobalScopes()
            ->where('customer_id', $service->customer_id)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'invoice_number', 'status', 'total', 'balance', 'due_date', 'zoho_invoice_id', 'last_synced_at', 'zoho_modified_at']);

        $payments = Payment::withoutGlobalScopes()
            ->where('customer_id', $service->customer_id)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'amount', 'status', 'zoho_sync_status', 'zoho_payment_id', 'confirmed_at', 'created_at']);

        $latestInvoiceSync = $invoices->max('last_synced_at');

        return [
            'service_id' => $service->id,
            'service_number' => $service->service_number,
            'customer_id' => $service->customer_id,
            'zoho_customer_id' => $service->zoho_customer_id ?? $customer?->zoho_contact_id,
            'customer_last_synced_at' => $customer?->last_synced_at,
            'billing_status' => $service->billing_status,
            'mrr' => $service->mrr,
            'source_of_truth' => 'zoho',
            'read_only' => true,
            'invoices' => $invoices,
            'payments' => $payments,
            'sync_timestamps' => [
                'customer_last_synced_at' => $customer?->last_synced_at?->toIso8601String(),
                'latest_invoice_synced_at' => $latestInvoiceSync
                    ? (is_string($latestInvoiceSync) ? $latestInvoiceSync : $latestInvoiceSync->toIso8601String())
                    : null,
            ],
            'note' => 'Balances and payments are reflected from synced Zoho data only. No local balance invention.',
        ];
    }
}
