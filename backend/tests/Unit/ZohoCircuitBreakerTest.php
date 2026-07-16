<?php

namespace Tests\Unit;

use App\Models\ZohoCircuitBreaker as BreakerModel;
use App\Services\Zoho\ZohoCircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZohoCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_opens_after_permanent_failure_threshold_and_can_resume(): void
    {
        config(['zoho.sync.circuit_breaker_threshold' => 2]);
        $service = app(ZohoCircuitBreaker::class);

        $service->recordFailure('customers', 'validation failed');
        $this->assertTrue($service->allows('customers'));
        $service->recordFailure('customers', 'validation failed');

        $this->assertFalse($service->allows('customers'));
        $this->assertSame('open', BreakerModel::where('sync_type', 'customers')->value('state'));

        $service->resume('customers');
        $this->assertTrue($service->allows('customers'));
        $this->assertSame('closed', BreakerModel::where('sync_type', 'customers')->value('state'));
    }
}
