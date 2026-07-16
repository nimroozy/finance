<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class PaymentConcurrencyTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    public function test_confirm_is_idempotent_under_repeat_calls(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod(), '20.0000'))
            ->assertCreated()
            ->json('data');

        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();
        $again = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk()->json('data');

        $this->assertSame(Payment::STATUS_SETTLED_PENDING_HANDOVER, $again['status']);
        $this->assertSame(1, Payment::withoutGlobalScopes()->where('uuid', $draft['uuid'])->count());
        $this->assertSame(1, DB::table('receipts')->where('payment_id', $draft['id'])->count());
    }
}
