<?php

namespace App\Services\Zoho;

use App\Models\ZohoConnection;
use App\Models\ZohoOrganization;
use RuntimeException;

class ZohoConnectionService
{
    public function __construct(
        protected ZohoApiClient $api,
        protected ZohoConfig $config,
    ) {}

    /**
     * Test connection via GET organizations and sync local org cache.
     *
     * @return array{ok:bool,organizations:list<array<string,mixed>>,selected:?array<string,mixed>}
     */
    public function testConnection(?ZohoConnection $connection = null): array
    {
        $connection ??= ZohoConnection::current();

        if (! $connection || ! $connection->isConnected()) {
            throw new RuntimeException('Zoho is not connected.');
        }

        $response = $this->api->get('organizations');
        $organizations = $response['organizations'] ?? [];

        if (! is_array($organizations)) {
            $organizations = [];
        }

        foreach ($organizations as $org) {
            ZohoOrganization::query()->updateOrCreate(
                ['zoho_org_id' => (string) ($org['organization_id'] ?? '')],
                [
                    'zoho_connection_id' => $connection->id,
                    'name' => (string) ($org['name'] ?? 'Unknown'),
                    'currency_code' => $org['currency_code'] ?? null,
                    'raw' => $org,
                ]
            );
        }

        $selected = null;
        if ($connection->organization_id) {
            $selected = ZohoOrganization::query()
                ->where('zoho_org_id', $connection->organization_id)
                ->first();
        }

        $connection->update([
            'status' => ZohoConnection::STATUS_CONNECTED,
            'last_error' => null,
            'organization_name' => $selected?->name ?? $connection->organization_name,
        ]);

        return [
            'ok' => true,
            'organizations' => $organizations,
            'selected' => $selected?->toArray(),
        ];
    }

    public function selectOrganization(string $zohoOrgId): ZohoConnection
    {
        $connection = ZohoConnection::current();

        if (! $connection) {
            throw new RuntimeException('Zoho is not connected.');
        }

        $org = ZohoOrganization::query()
            ->where('zoho_connection_id', $connection->id)
            ->where('zoho_org_id', $zohoOrgId)
            ->first();

        if (! $org) {
            // Allow selecting before local cache if id known from API
            $org = ZohoOrganization::query()->create([
                'zoho_connection_id' => $connection->id,
                'zoho_org_id' => $zohoOrgId,
                'name' => 'Organization '.$zohoOrgId,
                'is_selected' => true,
            ]);
        }

        ZohoOrganization::query()
            ->where('zoho_connection_id', $connection->id)
            ->update(['is_selected' => false]);

        $org->update(['is_selected' => true]);

        $connection->update([
            'organization_id' => $org->zoho_org_id,
            'organization_name' => $org->name,
        ]);

        return $connection->fresh();
    }

    /**
     * Update connection settings (data center / domains). Client secrets stay in .env only.
     *
     * @param  array{data_center?:string,organization_id?:string}  $data
     */
    public function updateSettings(array $data): ZohoConnection
    {
        $connection = ZohoConnection::current() ?? new ZohoConnection([
            'status' => ZohoConnection::STATUS_DISCONNECTED,
            'accounts_domain' => $this->config->accountsDomain(),
            'api_domain' => $this->config->apiDomain(),
            'data_center' => config('zoho.default_data_center', 'us'),
        ]);

        if (isset($data['data_center'])) {
            $dc = $this->config->resolveDataCenter($data['data_center']);
            $connection->data_center = $dc['code'];
            $connection->accounts_domain = $dc['accounts'];
            $connection->api_domain = $dc['api'];
        }

        if (array_key_exists('organization_id', $data) && filled($data['organization_id'])) {
            $connection->organization_id = $data['organization_id'];
        }

        if (! $connection->exists) {
            $connection->save();
        } else {
            $connection->save();
        }

        return $connection->fresh();
    }
}
