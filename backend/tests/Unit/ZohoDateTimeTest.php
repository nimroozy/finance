<?php

namespace Tests\Unit;

use App\Services\Zoho\ZohoDateTime;
use PHPUnit\Framework\TestCase;

class ZohoDateTimeTest extends TestCase
{
    public function test_formats_utc_with_offset_without_colon(): void
    {
        $value = ZohoDateTime::formatQueryTimestamp('2026-07-16T12:00:00+00:00');

        $this->assertSame('2026-07-16T12:00:00+0000', $value);
        $this->assertStringNotContainsString('+00:00', $value);
    }

    public function test_converts_positive_offset_to_utc(): void
    {
        $value = ZohoDateTime::formatQueryTimestamp('2026-07-16T12:00:00+04:30');

        $this->assertSame('2026-07-16T07:30:00+0000', $value);
        $this->assertDoesNotMatchRegularExpression('/[+-]\d{2}:\d{2}$/', $value);
    }

    public function test_converts_negative_offset_to_utc(): void
    {
        $value = ZohoDateTime::formatQueryTimestamp('2026-07-16T12:00:00-05:00');

        $this->assertSame('2026-07-16T17:00:00+0000', $value);
        $this->assertStringEndsNotWith('+00:00', $value);
    }

    public function test_parse_is_nullable_and_tolerant(): void
    {
        $this->assertNull(ZohoDateTime::parse(null));
        $this->assertNull(ZohoDateTime::parse('not-a-date'));
        $this->assertNotNull(ZohoDateTime::parse('2026-07-16T12:00:00Z'));
    }
}
