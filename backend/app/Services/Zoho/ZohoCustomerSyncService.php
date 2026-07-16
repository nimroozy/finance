<?php

namespace App\Services\Zoho;

use App\Models\Customer;
use App\Models\CustomerCustomField;
use App\Models\ZohoEntityMapping;
use App\Models\ZohoSyncCursor;
use App\Models\ZohoSyncJob;
use Illuminate\Support\Carbon;
use Throwable;

class ZohoCustomerSyncService
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
        $cursor = ZohoSyncCursor::query()->firstOrCreate(['entity' => 'customers']);
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
        $cursor = ZohoSyncCursor::query()->firstOrCreate(['entity' => 'customers']);
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
                        'progress' => array_merge($stats, ['page' => $page, 'entity' => 'customers']),
                    ]);
                }

                $response = $client->get('contacts', $query, 'customer');
                $contacts = $response['contacts'] ?? [];

                if (! is_array($contacts)) {
                    $contacts = [];
                }
                $stats['page_count']++;
                $stats['fetched'] += count($contacts);

                foreach ($contacts as $contact) {
                    try {
                        $result = $this->upsertContact($contact);
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

                if (count($contacts) === 0) {
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
     * @param  array<string, mixed>  $contact
     * @return 'created'|'updated'
     */
    public function upsertContact(array $contact): string
    {
        $zohoId = (string) ($contact['contact_id'] ?? '');

        if ($zohoId === '') {
            throw new \InvalidArgumentException('Zoho contact missing contact_id.');
        }

        $branchId = $this->branchMapping->resolveBranchId($contact);
        $isUnmapped = $branchId === null;

        $existing = Customer::withoutGlobalScopes()
            ->where('zoho_contact_id', $zohoId)
            ->first();

        $attributes = [
            'branch_id' => $branchId,
            'zoho_contact_id' => $zohoId,
            'customer_number' => $contact['contact_number'] ?? $contact['customer_number'] ?? null,
            'contact_name' => (string) ($contact['contact_name'] ?? $contact['customer_name'] ?? 'Unknown'),
            'company_name' => $contact['company_name'] ?? null,
            'phone' => $contact['phone'] ?? null,
            'mobile' => $contact['mobile'] ?? null,
            'whatsapp_number' => $contact['whatsapp'] ?? $contact['mobile'] ?? null,
            'email' => $contact['email'] ?? null,
            'billing_address' => $this->formatAddress($contact['billing_address'] ?? null),
            'shipping_address' => $this->formatAddress($contact['shipping_address'] ?? null),
            'currency' => substr((string) ($contact['currency_code'] ?? 'AFN'), 0, 3),
            'outstanding_receivable' => $this->decimal($contact['outstanding_receivable_amount'] ?? $contact['outstanding'] ?? 0),
            'payment_terms' => isset($contact['payment_terms_label'])
                ? (string) $contact['payment_terms_label']
                : (isset($contact['payment_terms']) ? (string) $contact['payment_terms'] : null),
            'status' => $isUnmapped
                ? Customer::STATUS_UNMAPPED
                : $this->mapStatus($contact),
            'reporting_tags' => $contact['reporting_tags'] ?? $contact['tags'] ?? null,
            'zoho_created_at' => ZohoDateTime::parse($contact['created_time'] ?? null),
            'zoho_modified_at' => ZohoDateTime::parse($contact['last_modified_time'] ?? null),
            'last_synced_at' => now(),
            'sync_status' => 'synced',
            'is_unmapped' => $isUnmapped,
        ];

        // Never mark successful without zoho_contact_id (already required above).
        if ($existing) {
            // Preserve manually mapped branch for previously unmapped → remapped only if mapping resolves
            if (! $isUnmapped || $existing->branch_id === null) {
                $existing->fill($attributes)->save();
            } else {
                unset($attributes['branch_id'], $attributes['is_unmapped'], $attributes['status']);
                $existing->fill($attributes)->save();
                if ($existing->branch_id) {
                    $existing->update([
                        'is_unmapped' => false,
                        'status' => $this->mapStatus($contact),
                    ]);
                }
            }
            $customer = $existing->fresh();
            $result = 'updated';
        } else {
            $customer = Customer::withoutGlobalScopes()->create($attributes);
            $result = 'created';
        }

        if (! $customer->zoho_contact_id) {
            throw new \RuntimeException('Refusing to complete customer sync without zoho_contact_id.');
        }

        ZohoEntityMapping::query()->updateOrCreate(
            [
                'entity_type' => 'customer',
                'zoho_id' => $zohoId,
            ],
            [
                'local_id' => $customer->id,
                'branch_id' => $customer->branch_id,
                'meta' => ['customer_number' => $customer->customer_number],
            ]
        );

        $this->syncCustomFields($customer, $contact['custom_fields'] ?? []);

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>|mixed  $fields
     */
    protected function syncCustomFields(Customer $customer, mixed $fields): void
    {
        if (! is_array($fields)) {
            return;
        }

        foreach ($fields as $field) {
            $apiName = (string) ($field['api_name'] ?? $field['index'] ?? '');
            if ($apiName === '') {
                continue;
            }

            CustomerCustomField::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'field_api_name' => $apiName,
                ],
                [
                    'field_label' => $field['label'] ?? $field['customfield_id'] ?? null,
                    'field_value' => isset($field['value']) ? (string) $field['value'] : null,
                ]
            );
        }
    }

    protected function formatAddress(mixed $address): ?string
    {
        if ($address === null) {
            return null;
        }

        if (is_string($address)) {
            return $address;
        }

        if (! is_array($address)) {
            return null;
        }

        return collect([
            $address['address'] ?? null,
            $address['street2'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['zip'] ?? null,
            $address['country'] ?? null,
        ])->filter()->implode(', ');
    }

    protected function mapStatus(array $contact): string
    {
        $status = strtolower((string) ($contact['status'] ?? 'active'));

        return match ($status) {
            'inactive', 'inactive_customer' => Customer::STATUS_INACTIVE,
            'deleted', 'archived' => Customer::STATUS_ARCHIVED,
            default => Customer::STATUS_ACTIVE,
        };
    }

    protected function decimal(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }

    protected function parseZohoDate(mixed $value): ?Carbon
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
