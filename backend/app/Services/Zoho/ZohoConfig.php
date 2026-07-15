<?php

namespace App\Services\Zoho;

use App\Models\SystemSetting;
use InvalidArgumentException;

class ZohoConfig
{
    public function clientId(): ?string
    {
        return config('zoho.client_id') ?: null;
    }

    public function clientSecret(): ?string
    {
        return config('zoho.client_secret') ?: null;
    }

    public function redirectUri(): string
    {
        return (string) config('zoho.redirect_uri');
    }

    public function organizationId(): ?string
    {
        return config('zoho.organization_id') ?: null;
    }

    public function oauthScopes(): string
    {
        return (string) config('zoho.oauth_scopes');
    }

    /**
     * @return array<string, array{accounts:string,api:string,label:string}>
     */
    public function dataCenters(): array
    {
        return (array) config('zoho.data_centers', []);
    }

    /**
     * @return array{accounts:string,api:string,label:string}
     */
    public function resolveDataCenter(?string $code = null): array
    {
        $code = $code ?: (string) config('zoho.default_data_center', 'us');
        $centers = $this->dataCenters();

        if (! isset($centers[$code])) {
            throw new InvalidArgumentException("Unknown Zoho data center [{$code}].");
        }

        return $centers[$code] + ['code' => $code];
    }

    public function accountsDomain(?string $dataCenter = null): string
    {
        if ($dataCenter) {
            return $this->resolveDataCenter($dataCenter)['accounts'];
        }

        return rtrim((string) config('zoho.accounts_domain'), '/');
    }

    public function apiDomain(?string $dataCenter = null): string
    {
        if ($dataCenter) {
            return $this->resolveDataCenter($dataCenter)['api'];
        }

        return rtrim((string) config('zoho.api_domain'), '/');
    }

    public function scheduleMinutes(string $key): int
    {
        $configKey = "zoho.schedule.{$key}";
        $settingKey = "zoho.schedule.{$key}";

        $fromSettings = SystemSetting::getValue($settingKey);

        if ($fromSettings !== null && is_numeric($fromSettings)) {
            return max(1, (int) $fromSettings);
        }

        return max(1, (int) config($configKey, 60));
    }

    public function assertCredentialsConfigured(): void
    {
        if (! $this->clientId() || ! $this->clientSecret()) {
            throw new InvalidArgumentException(
                'Zoho OAuth credentials are not configured. Set ZOHO_CLIENT_ID and ZOHO_CLIENT_SECRET in the environment.'
            );
        }
    }
}
