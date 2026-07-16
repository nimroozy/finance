<?php

namespace App\Services\Ownership;

use App\Models\CollectionVisit;
use App\Models\Customer;
use App\Models\CustomerWorkQueue;
use App\Models\Invoice;
use App\Models\OwnershipSyncEvent;
use App\Models\Payment;
use App\Models\PromiseToPay;
use App\Models\RecurringInvoiceRoutingLog;
use App\Services\AuditLogger;
use App\Support\Money;
use Carbon\Carbon;

class CustomerWorkQueueService
{
    public function __construct(
        private OwnershipResolutionService $resolver,
        private AuditLogger $audit,
    ) {}

    public function recalculate(Customer $customer): CustomerWorkQueue
    {
        $resolved = $this->resolver->resolve($customer);
        $source = $resolved['source'];
        if ($source === 'unassigned' && $this->hasConflictSignals($customer)) {
            $source = 'conflict';
        }

        $openInvoices = Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('balance', '>', 0)
            ->get();

        $total = '0.0000';
        $oldest = null;
        foreach ($openInvoices as $invoice) {
            $total = Money::add($total, (string) $invoice->balance);
            if ($invoice->due_date && ($oldest === null || $invoice->due_date->lt($oldest))) {
                $oldest = $invoice->due_date;
            }
        }

        $daysOverdue = 0;
        if ($oldest) {
            $daysOverdue = max(0, Carbon::parse($oldest)->startOfDay()->diffInDays(now()->startOfDay(), false));
        }

        $lastPayment = Payment::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereNotNull('confirmed_at')
            ->orderByDesc('confirmed_at')
            ->value('confirmed_at');

        $lastVisit = CollectionVisit::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->orderByDesc('visited_at')
            ->value('visited_at');

        $promise = PromiseToPay::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'due_soon', 'due_today', 'overdue'])
            ->orderByDesc('id')
            ->value('status');

        $queue = CustomerWorkQueue::query()->updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'branch_id' => $customer->branch_id,
                'effective_collector_id' => $resolved['collector_id'],
                'ownership_source' => $source,
                'total_open_balance' => Money::normalize($total),
                'open_invoice_count' => $openInvoices->count(),
                'oldest_due_date' => $oldest,
                'days_overdue' => $daysOverdue,
                'last_payment_date' => $lastPayment ? Carbon::parse($lastPayment)->toDateString() : null,
                'last_visit_at' => $lastVisit,
                'promise_status' => $promise,
                'priority' => $daysOverdue >= 30 ? 'urgent' : ($daysOverdue >= 14 ? 'high' : 'normal'),
                'work_status' => $openInvoices->count() > 0 ? 'open' : 'closed',
                'last_routed_at' => now(),
            ]
        );

        OwnershipSyncEvent::query()->create([
            'customer_id' => $customer->id,
            'event_type' => 'work_queue_recalculated',
            'source' => 'service',
            'payload' => [
                'ownership_source' => $source,
                'collector_id' => $resolved['collector_id'],
                'open_invoice_count' => $openInvoices->count(),
            ],
        ]);

        return $queue;
    }

    public function routeInvoice(Customer $customer, Invoice $invoice): RecurringInvoiceRoutingLog
    {
        $queue = $this->recalculate($customer);
        $resolved = $this->resolver->resolve($customer);
        $result = match ($queue->ownership_source) {
            'conflict' => 'conflict',
            'unassigned' => 'unassigned',
            default => 'routed',
        };

        $log = RecurringInvoiceRoutingLog::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'collector_id' => $resolved['collector_id'],
            'ownership_source' => $queue->ownership_source,
            'result' => $result,
            'details' => [
                'invoice_number' => $invoice->invoice_number,
                'balance' => (string) $invoice->balance,
                'branch_id' => $customer->branch_id,
            ],
        ]);

        $this->audit->log('ownership.invoice_routed', $invoice, null, [
            'result' => $result,
            'collector_id' => $resolved['collector_id'],
            'source' => $queue->ownership_source,
        ], $customer->branch_id);

        return $log;
    }

    private function hasConflictSignals(Customer $customer): bool
    {
        return $customer->branch_id === null;
    }
}
