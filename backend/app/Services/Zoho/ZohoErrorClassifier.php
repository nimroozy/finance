<?php

namespace App\Services\Zoho;

use Throwable;

class ZohoErrorClassifier
{
    public const PERMANENT = ['permanent_validation', 'invalid_request'];

    public static function classify(Throwable|string $error, ?int $httpStatus = null): string
    {
        if ($error instanceof ZohoApiException && $error->errorClass !== 'unknown') {
            return $error->errorClass;
        }

        $message = strtolower($error instanceof Throwable ? $error->getMessage() : $error);
        $status = $httpStatus ?? ($error instanceof ZohoApiException ? $error->httpStatus : null);

        if ($status === 401 || str_contains($message, 'unauthoriz') || str_contains($message, 'invalid token')) {
            return 'authentication';
        }
        if ($status === 403 || str_contains($message, 'permission') || str_contains($message, 'forbidden')) {
            return 'authorization';
        }
        if ($status === 429 || str_contains($message, 'rate limit')) {
            return 'rate_limit';
        }
        if ($status !== null && $status >= 500) {
            return 'server_error';
        }
        if (str_contains($message, 'timeout') || str_contains($message, 'connection')
            || str_contains($message, 'network') || str_contains($message, 'curl error')) {
            return 'network';
        }
        if ($status === 404 || str_contains($message, 'not found')) {
            return 'invalid_request';
        }
        if ($status === 400 || $status === 422 || str_contains($message, 'validation')
            || str_contains($message, 'last_modified_time') || str_contains($message, 'timestamp')) {
            return 'permanent_validation';
        }

        return 'unknown';
    }

    public static function isPermanent(Throwable|string $error, ?int $httpStatus = null): bool
    {
        return in_array(self::classify($error, $httpStatus), self::PERMANENT, true);
    }
}
