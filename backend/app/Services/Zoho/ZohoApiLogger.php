<?php

namespace App\Services\Zoho;

use App\Models\ZohoApiLog;
use App\Models\ZohoSyncJob;

class ZohoApiLogger
{
    public function log(array $attributes): ZohoApiLog
    {
        $attributes['error_message'] = isset($attributes['error_message'])
            ? $this->sanitize((string) $attributes['error_message'])
            : null;

        $attributes['created_at'] = $attributes['created_at'] ?? now();

        return ZohoApiLog::query()->create($attributes);
    }

    public function sanitize(string $message): string
    {
        $patterns = [
            '/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i',
            '/access_token["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
            '/refresh_token["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
            '/client_secret["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
            '/Authorization:\s*[^\s]+/i',
        ];

        $sanitized = preg_replace($patterns, '[REDACTED]', $message) ?? $message;

        return mb_substr($sanitized, 0, 2000);
    }

    public function attachJob(?ZohoSyncJob $job): ?int
    {
        return $job?->id;
    }
}
