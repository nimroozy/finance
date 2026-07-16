<?php

namespace App\Services\Zoho;

use RuntimeException;
use Throwable;

class ZohoApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $zohoCode = null,
        public readonly ?string $endpoint = null,
        public readonly mixed $body = null,
        public readonly string $errorClass = 'unknown',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }
}
