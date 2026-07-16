<?php

namespace Tests\Unit;

use App\Services\Zoho\ZohoErrorClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ZohoErrorClassifierTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_classifies_errors(string $message, ?int $status, string $expected): void
    {
        $this->assertSame($expected, ZohoErrorClassifier::classify($message, $status));
    }

    public static function cases(): array
    {
        return [
            ['invalid token', 401, 'authentication'],
            ['forbidden', 403, 'authorization'],
            ['rate limited', 429, 'rate_limit'],
            ['server exploded', 503, 'server_error'],
            ['connection timeout', null, 'network'],
            ['record not found', 404, 'invalid_request'],
            ['last_modified_time has invalid timestamp', 400, 'permanent_validation'],
            ['something unexpected', null, 'unknown'],
        ];
    }
}
