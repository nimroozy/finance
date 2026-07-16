<?php

namespace App\Services\Zoho;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceCustomField;
use App\Models\ZohoEntityMapping;
use App\Models\ZohoSyncCursor;
use App\Models\ZohoSyncJob;
use Illuminate\Support\Carbon;
use Throwable;

class ZohoInvoiceSyncService
{
    public function __construct(
        protected ZohoApiClient $api,
        protected ZohoBranchMappingService $branchMapping,
        protected ZohoConfig $config,
    ) {}

    /**
     * @return array{processed:int,created:int,updated:int,failed:int}
     */
    public function syncFull(?ZohoSyncJob $job = null): array
    {
        return $this->sync(null, $job);
    }

    /**
     * @return array{processed:int,created:int,updated:int,failed:int}
     */
    public function syncIncremental(?ZohoSyncJob $job = null): array
    {
        $cursor = ZohoSyncCursor::query()->firstOrCreate(['entity' => 'invoices']);
        $since = $cursor->successful_cursor?->copy()
            ->subMinutes((int) config('zoho.sync.cursor_overlap_minutes', 2));

        return $this->sync($since, $job);
    }

    /**
     * @return array{processed:int,created:int,updated:int,failed:int}
     */
    public function sync(?Carbon $lastModifiedTime = null, ?ZohoSyncJob $job = null): array
    {
        $client = $this->api->forSyncJob($job);
        $cursor = ZohoSyncCursor::query()->firstOrCreate(['entity' => 'invoices']);
        $requestedTo = now()->utc();
        $stats = [
            'requested_from' => $lastModifiedTime ? ZohoDateTime::formatQueryTimestamp($lastModifiedTime) : null,
            'requested_to' => ZohoDateTime::formatQueryTimestamp($requestedTo),
            'successful_cursor' => $cursor->successful_cursor?->toIso8601String(),
            'page_count' => 0, 'fetched' => 0, 'processed' => 0, 'created' => 0,
            'updated' => 0, 'skipped' => 0, 'failed' => 0,
        ];
        $page = 1;
        $perPage = (int) config('zoho.http.per_page', 200);
        $hasMore = true;

        $cursor->update([
            'last_requested_from' => $lastModifiedTime,
            'last_requested_to' => $requestedTo,
            'last_error' => null,
        ]);
        $job?->update(['cursor_from' => $lastModifiedTime, 'cursor_to' => $requestedTo]);

        try {
            while ($hasMore) {
                $query = [
                    'page' => $page,
                    'per_page' => $perPage,
                ];

                if ($lastModifiedTime) {
                    $query['last_modified_time'] = ZohoDateTime::formatQueryTimestamp($lastModifiedTime);
                }

                if ($job) {
                    $job->update([
                        'progress' => array_merge($stats, ['page' => $page, 'entity' => 'invoices']),
                    ]);
                }

                $response = $client->get('invoices', $query, 'invoice');
                $invoices = $response['invoices'] ?? [];

                if (! is_array($invoices)) {
                    $invoices = [];
                }
                $stats['page_count']++;
                $stats['fetched'] += count($invoices);

                foreach ($invoices as $invoice) {
                    try {
                        $result = $this->upsertInvoice($invoice);
                        $stats['processed']++;
                        $stats[$result]++;
                    } catch (Throwable $e) {
                        $stats['processed']++;
                        $stats['failed']++;
                    }
                }

                $pageContext = $response['page_context'] ?? [];
                $hasMore = (bool) ($pageContext['has_more_page'] ?? false);
                $page++;

                if (count($invoices) === 0) {
                    $hasMore = false;
                }
            }
        } catch (Throwable $e) {
            $cursor->update(['last_error' => $e->getMessage(), 'meta' => $stats]);
            $job?->update(['progress' => $stats, 'stats' => $stats]);
            throw $e;
        }

        if ($stats['failed'] === 0) {
            $cursor->update([
                'successful_cursor' => $requestedTo,
                'last_success_at' => now(),
                'last_error' => null,
                'meta' => $stats,
            ]);
            $stats['successful_cursor'] = ZohoDateTime::formatQueryTimestamp($requestedTo);
        }
        $job?->update(['progress' => $stats, 'stats' => $stats]);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return 'created'|'updated'
     */
    public function upsertInvoice(array $invoice): string
    {
        $zohoId = (string) ($invoice['invoice_id'] ?? '');

        if ($zohoId === '') {
            throw new \InvalidArgumentException('Zoho invoice missing invoice_id.');
        }

        $zohoContactId = (string) ($invoice['customer_id'] ?? $invoice['contact_id'] ?? '');

        if ($zohoContactId === '') {
            throw new \InvalidArgumentException('Zoho invoice missing customer_id.');
        }

        $customer = Customer::withoutGlobalScopes()
            ->where('zoho_contact_id', $zohoContactId)
            ->first();

        if (! $customer) {
            // Create a minimal placeholder customer so invoice can be stored with zoho ids.
            $branchId = $this->branchMapping->resolveBranchId($invoice);
            $customer = Customer::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'zoho_contact_id' => $zohoContactId,
                'contact_name' => (string) ($invoice['customer_name'] ?? 'Unknown'),
                'currency' => substr((string) ($invoice['currency_code'] ?? 'AFN'), 0, 3),
                'status' => $branchId ? Customer::STATUS_ACTIVE : Customer::STATUS_UNMAPPED,
                'is_unmapped' => $branchId === null,
                'sync_status' => 'placeholder',
                'last_synced_at' => now(),
            ]);
        }

        $branchId = $this->branchMapping->resolveBranchId($invoice) ?? $customer->branch_id;
        $locationId = $this->branchMapping->extractLocationId($invoice);

        $existing = Invoice::withoutGlobalScopes()
            ->where('zoho_invoice_id', $zohoId)
            ->first();

        $attributes = [
            'branch_id' => $branchId,
            'zoho_location_id' => $locationId,
            'customer_id' => $customer->id,
            'zoho_invoice_id' => $zohoId,
            'invoice_number' => (string) ($invoice['invoice_number'] ?? $zohoId),
            'invoice_date' => $this->parseDate($invoice['date'] ?? null),
            'due_date' => $this->parseDate($invoice['due_date'] ?? null),
            'status' => strtolower((string) ($invoice['status'] ?? 'draft')),
            'currency' => substr((string) ($invoice['currency_code'] ?? $customer->currency ?? 'AFN'), 0, 3),
            'total' => $this->decimal($invoice['total'] ?? 0),
            'amount_paid' => $this->decimal($invoice['payment_made'] ?? $invoice['amount_paid'] ?? 0),
            'credits_applied' => $this->decimal($invoice['credits_applied'] ?? 0),
            'balance' => $this->decimal($invoice['balance'] ?? 0),
            'reporting_tags' => $invoice['reporting_tags'] ?? $invoice['tags'] ?? null,
            'zoho_modified_at' => ZohoDateTime::parse($invoice['last_modified_time'] ?? null),
            'last_synced_at' => now(),
            'sync_status' => 'synced',
        ];

        if ($existing) {
            $existing->fill($attributes)->save();
            $model = $existing->fresh();
            $result = 'updated';
        } else {
            $model = Invoice::withoutGlobalScopes()->create($attributes);
            $result = 'created';
        }

        if (! $model->zoho_invoice_id) {
            throw new \RuntimeException('Refusing to complete invoice sync without zoho_invoice_id.');
        }

        ZohoEntityMapping::query()->updateOrCreate(
            [
                'entity_type' => 'invoice',
                'zoho_id' => $zohoId,
            ],
            [
                'local_id' => $model->id,
                'branch_id' => $model->branch_id,
                'meta' => ['invoice_number' => $model->invoice_number],
            ]
        );

        $this->syncCustomFields($model, $invoice['custom_fields'] ?? []);

        if ($locationId && $branchId && ($customer->is_unmapped || $customer->branch_id === null)) {
            if (! $this->branchMapping->customerHasBranchConflict($customer, $branchId)) {
                $customer->update([
                    'branch_id' => $branchId,
                    'zoho_location_id' => $customer->zoho_location_id ?: $locationId,
                    'is_unmapped' => false,
                    'status' => $customer->status === Customer::STATUS_UNMAPPED
                        ? Customer::STATUS_ACTIVE
                        : $customer->status,
                ]);
            }
        }

        // Keep customer outstanding roughly in sync when invoice balances change
        if ($customer->outstanding_receivable === null || $customer->sync_status === 'placeholder') {
            // no-op — customer sync owns outstanding
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>|mixed  $fields
     */
    protected function syncCustomFields(Invoice $invoice, mixed $fields): void
    {
        if (! is_array($fields)) {
            return;
        }

        foreach ($fields as $field) {
            $apiName = (string) ($field['api_name'] ?? $field['index'] ?? '');
            if ($apiName === '') {
                continue;
            }

            InvoiceCustomField::query()->updateOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'field_api_name' => $apiName,
                ],
                [
                    'field_label' => $field['label'] ?? null,
                    'field_value' => isset($field['value']) ? (string) $field['value'] : null,
                ]
            );
        }
    }

    protected function decimal(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }

    protected function parseDate(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    protected function parseDateTime(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
