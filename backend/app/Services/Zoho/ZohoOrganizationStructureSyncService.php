<?php

namespace App\Services\Zoho;

use App\Models\ZohoAccount;
use App\Models\ZohoConnection;
use App\Models\ZohoLocation;
use App\Models\ZohoPaymentMode;
use App\Models\ZohoReportingTag;
use App\Models\ZohoReportingTagOption;
use App\Models\ZohoSyncCursor;
use Throwable;

class ZohoOrganizationStructureSyncService
{
    public function __construct(
        protected ZohoApiClient $api,
        protected ZohoSyncHeartbeatService $heartbeat,
    ) {}

    public function sync(bool $apply = true): array
    {
        $this->heartbeat->recordStart('structure_sync', ['apply' => $apply]);
        $organizationId = (string) (ZohoConnection::current()?->organization_id ?? config('zoho.organization_id'));
        $stats = ['locations' => 0, 'tags' => 0, 'tag_options' => 0, 'payment_modes' => 0, 'accounts' => 0];

        try {
            $locations = $this->api->get('locations')['locations'] ?? [];
            foreach (is_array($locations) ? $locations : [] as $location) {
                $id = (string) ($location['location_id'] ?? $location['branch_id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $stats['locations']++;
                if ($apply) {
                    ZohoLocation::query()->updateOrCreate(['zoho_location_id' => $id], [
                        'organization_id' => $organizationId,
                        'name' => (string) ($location['location_name'] ?? $location['name'] ?? $id),
                        'code' => $location['location_code'] ?? $location['code'] ?? null,
                        'is_active' => ($location['status'] ?? 'active') !== 'inactive',
                        'is_primary' => (bool) ($location['is_primary'] ?? false),
                        'raw' => $location, 'sync_status' => 'synced', 'last_synced_at' => now(),
                    ]);
                }
            }

            $tagResponse = $this->api->get('settings/tags');
            $tags = $tagResponse['reporting_tags'] ?? $tagResponse['tags'] ?? [];
            foreach (is_array($tags) ? $tags : [] as $tag) {
                $tagId = (string) ($tag['tag_id'] ?? $tag['reporting_tag_id'] ?? '');
                if ($tagId === '') {
                    continue;
                }
                $stats['tags']++;
                if ($apply) {
                    ZohoReportingTag::query()->updateOrCreate(['zoho_tag_id' => $tagId], [
                        'organization_id' => $organizationId,
                        'name' => (string) ($tag['tag_name'] ?? $tag['name'] ?? $tagId),
                        'associated_with' => $tag['associated_with'] ?? null,
                        'is_active' => ($tag['status'] ?? 'active') !== 'inactive',
                        'raw' => $tag, 'sync_status' => 'synced', 'last_synced_at' => now(),
                    ]);
                }
                foreach ($this->normalizeOptions($tagId, $tag['tag_options'] ?? $tag['options'] ?? []) as $option) {
                    $stats['tag_options']++;
                    if ($apply) {
                        ZohoReportingTagOption::query()->updateOrCreate(
                            ['zoho_tag_option_id' => $option['id']],
                            [
                                'zoho_tag_id' => $tagId, 'name' => $option['name'],
                                'is_active' => $option['is_active'], 'raw' => $option['raw'],
                                'last_synced_at' => now(),
                            ]
                        );
                    }
                }
            }

            $modes = $this->api->get('settings/paymentmodes')['payment_modes'] ?? [];
            foreach (is_array($modes) ? $modes : [] as $mode) {
                $id = (string) ($mode['payment_mode_id'] ?? $mode['mode_id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $stats['payment_modes']++;
                if ($apply) {
                    ZohoPaymentMode::query()->updateOrCreate(['zoho_payment_mode_id' => $id], [
                        'organization_id' => $organizationId,
                        'name' => (string) ($mode['name'] ?? $mode['payment_mode'] ?? $id),
                        'is_active' => ($mode['status'] ?? 'active') !== 'inactive',
                        'raw' => $mode, 'last_synced_at' => now(),
                    ]);
                }
            }

            $pageLimit = max(1, (int) config('zoho.sync.chart_of_accounts_page_limit', 10));
            for ($page = 1; $page <= $pageLimit; $page++) {
                $response = $this->api->get('chartofaccounts', ['page' => $page, 'per_page' => 200]);
                $accounts = $response['chartofaccounts'] ?? $response['accounts'] ?? [];
                foreach (is_array($accounts) ? $accounts : [] as $account) {
                    $id = (string) ($account['account_id'] ?? '');
                    if ($id === '') {
                        continue;
                    }
                    $stats['accounts']++;
                    if ($apply) {
                        ZohoAccount::query()->updateOrCreate(['zoho_account_id' => $id], [
                            'organization_id' => $organizationId,
                            'name' => (string) ($account['account_name'] ?? $account['name'] ?? $id),
                            'account_type' => (string) ($account['account_type'] ?? 'unknown'),
                            'account_code' => $account['account_code'] ?? null,
                            'is_active' => ($account['is_active'] ?? true) !== false,
                            'raw' => $account, 'last_synced_at' => now(),
                        ]);
                    }
                }
                if (! ($response['page_context']['has_more_page'] ?? false)) {
                    break;
                }
            }

            if ($apply) {
                ZohoSyncCursor::query()->updateOrCreate(['entity' => 'structure'], [
                    'successful_cursor' => now(), 'last_success_at' => now(), 'last_error' => null, 'meta' => $stats,
                ]);
            }
            $this->heartbeat->recordSuccess('structure_sync', $stats);

            return $stats;
        } catch (Throwable $e) {
            ZohoSyncCursor::query()->updateOrCreate(['entity' => 'structure'], ['last_error' => $e->getMessage(), 'meta' => $stats]);
            $this->heartbeat->recordFailure('structure_sync', $e, $stats);
            throw $e;
        }
    }

    /**
     * When Zoho supplies option names without IDs, a stable tag-scoped hash is
     * used. It is intentionally prefixed so it cannot collide with a Zoho ID.
     *
     * @return list<array{id:string,name:string,is_active:bool,raw:mixed}>
     */
    protected function normalizeOptions(string $tagId, mixed $options): array
    {
        if (is_string($options)) {
            $options = array_map('trim', explode(',', $options));
        }
        if (! is_array($options)) {
            return [];
        }

        return collect($options)->filter(fn ($option) => filled($option))->map(function ($option) use ($tagId) {
            $row = is_array($option) ? $option : ['name' => (string) $option];
            $name = (string) ($row['tag_option_name'] ?? $row['option_name'] ?? $row['name'] ?? '');
            $id = (string) ($row['tag_option_id'] ?? $row['option_id'] ?? '');
            if ($id === '') {
                $id = 'synthetic:'.hash('sha256', $tagId."\0".mb_strtolower($name));
            }

            return ['id' => $id, 'name' => $name, 'is_active' => ($row['status'] ?? 'active') !== 'inactive', 'raw' => $row];
        })->values()->all();
    }
}
