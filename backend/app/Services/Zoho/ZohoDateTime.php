<?php

namespace App\Services\Zoho;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Throwable;

/**
 * Central Zoho date handling.
 *
 * Zoho Books query parameters require a numeric UTC offset without a colon
 * (for example +0000); the ISO-8601 +00:00 form is rejected by some endpoints.
 */
class ZohoDateTime
{
    public static function formatQueryTimestamp(CarbonInterface|DateTimeInterface|string $dt): string
    {
        $value = is_string($dt) ? Carbon::parse($dt) : Carbon::instance($dt);

        return $value->utc()->format('Y-m-d\TH:i:sO');
    }

    public static function parse(?string $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
