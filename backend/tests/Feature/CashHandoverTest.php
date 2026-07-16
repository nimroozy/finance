<?php

namespace Tests\Feature;

use App\Models\BranchCashbox;
use App\Models\CashHandoverItem;
use App\Models\CashHandoverRequest;
use App\Models\CollectorWallet;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class CashHandoverTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    public function test_eligible_rejects_non_cash_and_includes_cash(): void
    {
        config(['zoho.payments.dry_run' => true]);
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $cashDraft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), '50.0000'
        ))->assertCreated()->json('data');
        $cash = $this->postJson('/api/v1/payments/'.$cashDraft['uuid'].'/confirm')->assertOk()->json('data');

        $bankDraft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->bankMethod(), '20.0000', ['external_reference' => 'BNK-H1']
        ))->assertCreated()->json('data');
        $this->postJson('/api/v1/payments/'.$bankDraft['uuid'].'/confirm')->assertOk();

        $eligible = $this->getJson('/api/v1/cash-handovers/eligible?branch_id='.$branch->id)
            ->assertOk()
            ->json('data');

        $ids = collect($eligible)->pluck('id');
        $this->assertTrue($ids->contains($cash['id']));
        $this->assertFalse($ids->contains(
            Payment::withoutGlobalScopes()->where('uuid', $bankDraft['uuid'])->value('id')
        ));
    }

    public function test_full_approval_debits_wallet_and_credits_cashbox(): void
    {
        config(['zoho.payments.dry_run' => true]);
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draftPayment = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), '75.0000'
        ))->assertCreated()->json('data');
        $payment = $this->postJson('/api/v1/payments/'.$draftPayment['uuid'].'/confirm')->assertOk()->json('data');

        $walletBefore = CollectorWallet::withoutGlobalScopes()
            ->where('collector_id', $collector->id)->value('balance');
        $this->assertSame('75.0000', Money::normalize((string) $walletBefore));

        $handover = $this->postJson('/api/v1/cash-handovers/draft', [
            'branch_id' => $branch->id,
            'payment_ids' => [$payment['id']],
            'declared_amount' => '75.0000',
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('cash_handover_requests', [
            'id' => $handover['id'],
            'status' => CashHandoverRequest::STATUS_DRAFT,
        ]);

        // No cashbox movement yet
        $this->assertSame(0, BranchCashbox::withoutGlobalScopes()->where('branch_id', $branch->id)->sum('balance'));

        $submitted = $this->postJson('/api/v1/cash-handovers/'.$handover['id'].'/submit')->assertOk()->json('data');
        $this->assertSame(CashHandoverRequest::STATUS_SUBMITTED, $submitted['status']);

        $this->actingAsUser($manager);
        $approved = $this->postJson('/api/v1/cash-handovers/'.$handover['id'].'/approve', [
            'counted_amount' => '75.0000',
            'approved_amount' => '75.0000',
        ])->assertOk()->json('data');

        $this->assertSame(CashHandoverRequest::STATUS_APPROVED, $approved['status']);
        $this->assertSame('0.0000', Money::normalize((string) CollectorWallet::withoutGlobalScopes()
            ->where('collector_id', $collector->id)->value('balance')));
        $this->assertSame('75.0000', Money::normalize((string) BranchCashbox::withoutGlobalScopes()
            ->where('branch_id', $branch->id)->value('balance')));
        $this->assertSame('handed_over', Payment::withoutGlobalScopes()->find($payment['id'])->handover_status);
    }

    public function test_rejection_does_not_change_balances(): void
    {
        config(['zoho.payments.dry_run' => true]);
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draftPayment = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), '40.0000'
        ))->assertCreated()->json('data');
        $payment = $this->postJson('/api/v1/payments/'.$draftPayment['uuid'].'/confirm')->assertOk()->json('data');

        $handover = $this->postJson('/api/v1/cash-handovers/draft', [
            'branch_id' => $branch->id,
            'payment_ids' => [$payment['id']],
            'declared_amount' => '40.0000',
        ])->assertCreated()->json('data');
        $this->postJson('/api/v1/cash-handovers/'.$handover['id'].'/submit')->assertOk();

        $this->actingAsUser($manager);
        $this->postJson('/api/v1/cash-handovers/'.$handover['id'].'/reject', [
            'notes' => 'Count mismatch STAGE5-TEST',
        ])->assertOk();

        $this->assertSame('40.0000', Money::normalize((string) CollectorWallet::withoutGlobalScopes()
            ->where('collector_id', $collector->id)->value('balance')));
        $this->assertSame('0.0000', Money::normalize((string) (BranchCashbox::withoutGlobalScopes()
            ->where('branch_id', $branch->id)->value('balance') ?? '0')));
        $this->assertSame('pending_handover', Payment::withoutGlobalScopes()->find($payment['id'])->handover_status);
    }

    public function test_duplicate_payment_cannot_enter_second_handover(): void
    {
        config(['zoho.payments.dry_run' => true]);
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draftPayment = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), '15.0000'
        ))->assertCreated()->json('data');
        $payment = $this->postJson('/api/v1/payments/'.$draftPayment['uuid'].'/confirm')->assertOk()->json('data');

        $this->postJson('/api/v1/cash-handovers/draft', [
            'branch_id' => $branch->id,
            'payment_ids' => [$payment['id']],
            'declared_amount' => '15.0000',
        ])->assertCreated();

        $this->postJson('/api/v1/cash-handovers/draft', [
            'branch_id' => $branch->id,
            'payment_ids' => [$payment['id']],
            'declared_amount' => '15.0000',
        ])->assertStatus(422);

        $this->assertSame(1, CashHandoverItem::query()->where('payment_id', $payment['id'])->where('is_active', true)->count());
    }

    public function test_collector_cannot_see_other_branch_handovers(): void
    {
        config(['zoho.payments.dry_run' => true]);
        $branchA = $this->makeBranch(['code' => 'HA'.Str::upper(Str::random(2))]);
        $branchB = $this->makeBranch(['code' => 'HB'.Str::upper(Str::random(2))]);
        $managerA = $this->makeManager($branchA);
        $managerB = $this->makeManager($branchB);
        [$collectorAUser, $collectorA] = $this->makeCollectorUser($branchA);
        $customerA = $this->makeCustomer($branchA);
        $invoiceA = $this->makeInvoiceForCustomer($customerA);
        $this->makeActiveAssignment($managerA, $customerA, $collectorA);

        $this->actingAsUser($collectorAUser);
        $draftPayment = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customerA, $collectorA, $invoiceA, $this->cashMethod(), '12.0000'
        ))->assertCreated()->json('data');
        $payment = $this->postJson('/api/v1/payments/'.$draftPayment['uuid'].'/confirm')->assertOk()->json('data');
        $handover = $this->postJson('/api/v1/cash-handovers/draft', [
            'branch_id' => $branchA->id,
            'payment_ids' => [$payment['id']],
            'declared_amount' => '12.0000',
        ])->assertCreated()->json('data');

        $this->actingAsUser($managerB);
        $this->getJson('/api/v1/cash-handovers/'.$handover['id'])->assertStatus(404);
    }
}
