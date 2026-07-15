<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    public function test_same_idempotency_key_returns_same_draft(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $key = (string) Str::uuid();
        $payload = $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod(), '100.0000', [
            'idempotency_key' => $key,
        ]);

        $first = $this->postJson('/api/v1/payments/draft', $payload)->assertCreated()->json('data');
        $second = $this->postJson('/api/v1/payments/draft', $payload)->assertCreated()->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, \App\Models\Payment::withoutGlobalScopes()->where('idempotency_key', $key)->count());
    }

    public function test_same_key_different_payload_conflicts(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $key = (string) Str::uuid();
        $payload = $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod(), '100.0000', [
            'idempotency_key' => $key,
        ]);
        $this->postJson('/api/v1/payments/draft', $payload)->assertCreated();

        $payload['amount'] = '150.0000';
        $payload['allocations'][0]['amount'] = '150.0000';
        $this->postJson('/api/v1/payments/draft', $payload)->assertStatus(422);
    }
}
