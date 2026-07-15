<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentReversal;
use App\Models\Receipt;
use App\Models\User;
use App\Models\ZohoPaymentModeMapping;
use App\Services\Assignments\AssignmentService;
use App\Services\Payments\PaymentReversalService;
use App\Services\Payments\PaymentService;
use App\Services\Zoho\ZohoApiClient;
use App\Services\Zoho\ZohoPaymentSyncService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Controlled Stage 4 live Zoho verification using labeled TEST records only.
 * Keeps ZOHO_PAYMENT_DRY_RUN=true globally; live HTTP only when --live-zoho is passed
 * and only for the command process (forceLive sync/void).
 */
class Stage4LiveZohoVerifyCommand extends Command
{
    protected $signature = 'app:stage4-live-zoho-verify
                            {--password-file= : Password file for labeled smoke users}
                            {--live-zoho : Required. Creates a real Zoho payment for STAGE5 TEST records only}
                            {--amount=10.0000 : Payment amount in AFN}
                            {--keep : Keep local labeled records (recommended for audit)}';

    protected $description = 'Controlled live Zoho payment verification with dedicated test customer/invoice';

    public function handle(
        ZohoApiClient $zoho,
        ZohoPaymentSyncService $sync,
        AssignmentService $assignments,
        PaymentService $payments,
        PaymentReversalService $reversals,
    ): int {
        if (! $this->option('live-zoho')) {
            $this->error('--live-zoho is required. Refusing to create a live Zoho payment without it.');

            return self::FAILURE;
        }

        if (! config('zoho.payments.dry_run')) {
            $this->warn('ZOHO_PAYMENT_DRY_RUN is false globally. Queue workers may also post live.');
            if (! $this->confirm('Continue with global dry-run already disabled?', false)) {
                return self::FAILURE;
            }
        }

        $password = $this->readPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $amount = (string) $this->option('amount');
        $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        $results = [];
        $ref = 'S4LIVE-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));

        try {
            $this->ensureCashModeMapping();
            $this->ensureDepositAccount($zoho);

            $branch = Branch::query()->where('code', '01')->first()
                ?: Branch::query()->where('is_active', true)->orderBy('id')->firstOrFail();

            $manager = $this->upsertUser(
                'stage4.live.manager@test.local',
                'stage4_live_manager',
                'STAGE4-LIVE Manager',
                User::ROLE_BRANCH_MANAGER,
                $password,
                [$branch->id]
            );

            [$collectorUser, $collector] = $this->upsertCollector(
                'stage4.live.collector@test.local',
                'stage4_live_collector',
                'STAGE4-LIVE Collector',
                $password,
                $branch
            );

            $this->line('Creating/ensuring Zoho test contact...');
            $contact = $this->ensureZohoTestContact($zoho);
            $zohoContactId = (string) $contact['contact_id'];
            $results['zoho_contact_id'] = $zohoContactId;

            $this->line('Creating Zoho test invoice ('.$amount.' AFN)...');
            $invoiceZoho = $this->createZohoTestInvoice($zoho, $zohoContactId, $amount);
            $zohoInvoiceId = (string) $invoiceZoho['invoice_id'];
            $invoiceNumber = (string) ($invoiceZoho['invoice_number'] ?? $zohoInvoiceId);
            $results['zoho_invoice_id'] = $zohoInvoiceId;
            $results['invoice_number'] = $invoiceNumber;

            $customer = Customer::withoutGlobalScopes()->updateOrCreate(
                ['zoho_contact_id' => $zohoContactId],
                [
                    'branch_id' => $branch->id,
                    'customer_number' => (string) ($contact['contact_number'] ?? 'STAGE5-TEST-C'),
                    'contact_name' => 'STAGE5 TEST CUSTOMER - DO NOT USE',
                    'currency' => 'AFN',
                    'outstanding_receivable' => $amount,
                    'status' => Customer::STATUS_ACTIVE,
                    'is_unmapped' => false,
                    'sync_status' => 'synced',
                ]
            );
            $results['local_customer_id'] = $customer->id;
            $results['unmapped'] = $customer->is_unmapped === false;

            $invoice = Invoice::withoutGlobalScopes()->updateOrCreate(
                ['zoho_invoice_id' => $zohoInvoiceId],
                [
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => now()->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'status' => 'sent',
                    'currency' => 'AFN',
                    'total' => $amount,
                    'amount_paid' => '0.0000',
                    'credits_applied' => '0.0000',
                    'balance' => $amount,
                    'sync_status' => 'synced',
                ]
            );
            $results['local_invoice_id'] = $invoice->id;
            $results['balance_matches'] = (string) $invoice->balance === $amount;

            $assignment = $assignments->create([
                'customer_id' => $customer->id,
                'collector_id' => $collector->id,
                'notes' => 'STAGE4-LIVE assignment '.$ref,
            ], $manager);

            $cash = PaymentMethod::query()->where('code', 'cash')->firstOrFail();
            $draft = $payments->createDraft([
                'customer_id' => $customer->id,
                'collector_id' => $collector->id,
                'assignment_id' => $assignment->id,
                'payment_method_id' => $cash->id,
                'amount' => $amount,
                'currency' => 'AFN',
                'allocations' => [
                    ['invoice_id' => $invoice->id, 'amount' => $amount],
                ],
                'idempotency_key' => $ref.'-draft',
                'notes' => 'STAGE4 LIVE ZOHO TEST '.$ref.' — DO NOT USE FOR PRODUCTION CUSTOMERS',
            ], $collectorUser);

            // Force unique payment_reference for Zoho duplicate detection.
            $draft->payment_reference = $ref;
            $draft->save();

            $confirmed = $payments->confirm($draft->uuid, $collectorUser, $ref.'-confirm');
            $results['payment_confirmed'] = $confirmed->isConfirmed();
            $results['payment_uuid'] = $confirmed->uuid;
            $results['payment_reference'] = $confirmed->payment_reference;
            $results['receipt_issued'] = Receipt::withoutGlobalScopes()->where('payment_id', $confirmed->id)->exists();

            $walletBalance = DB::table('collector_wallets')
                ->where('collector_id', $collector->id)
                ->where('branch_id', $branch->id)
                ->value('balance');
            $results['wallet_credited'] = $walletBalance !== null
                && bccomp((string) $walletBalance, '0', 4) >= 0;

            // Scoped live sync — does not change env for collectors/queue-workers.
            $this->line('Force-live Zoho sync for '.$confirmed->payment_reference.'...');
            $synced = $sync->sync($confirmed->fresh(), forceLive: true);
            $results['zoho_payment_id'] = $synced->zoho_payment_id;
            $results['zoho_sync_status'] = $synced->zoho_sync_status;
            $results['live_sync'] = $synced->zoho_sync_status === Payment::ZOHO_SYNCED
                && filled($synced->zoho_payment_id)
                && ! str_starts_with((string) $synced->zoho_payment_id, 'DRYRUN-');

            $reconcile = $sync->reconcileAgainstZoho($synced->fresh());
            $results['reconciliation'] = $reconcile['status'];
            $results['reconcile_matched'] = $reconcile['status'] === 'matched';

            // Duplicate retry must not create a second payment.
            $retried = $sync->sync($synced->fresh(), forceLive: true);
            $results['retry_same_id'] = $retried->zoho_payment_id === $synced->zoho_payment_id;
            $existing = $sync->findExistingByReference((string) $synced->payment_reference);
            $results['retry_no_duplicate'] = $existing !== null
                && (string) ($existing['payment_id'] ?? '') === (string) $synced->zoho_payment_id;

            // Verify invoice balance zero in Zoho.
            $zohoInv = $zoho->get('invoices/'.$zohoInvoiceId, [], 'invoice');
            $invPayload = $zohoInv['invoice'] ?? $zohoInv;
            $zohoBalance = (string) ($invPayload['balance'] ?? 'n/a');
            $results['zoho_invoice_balance'] = $zohoBalance;
            $results['zoho_invoice_zero'] = bccomp($zohoBalance, '0', 4) === 0;

            // Live reversal
            $this->line('Requesting/approving live reversal...');
            $reversal = $reversals->request(
                $synced->fresh(),
                $collectorUser,
                'STAGE4 LIVE ZOHO TEST reversal '.$ref
            );
            $approved = $reversals->approve($reversal, $manager, forceLiveZoho: true);
            $results['reversal_approved'] = $approved->status === PaymentReversal::STATUS_APPROVED;
            $results['payment_not_deleted'] = Payment::withoutGlobalScopes()->whereKey($synced->id)->exists()
                && $synced->fresh()->status === Payment::STATUS_REVERSED;
            $results['wallet_compensated'] = (bool) $approved->wallet_reversed;

            $receipt = Receipt::withoutGlobalScopes()->where('payment_id', $synced->id)->first();
            $results['receipt_reversed'] = $receipt && $receipt->status === Receipt::STATUS_VOIDED;

            $zohoInvAfter = $zoho->get('invoices/'.$zohoInvoiceId, [], 'invoice');
            $balAfter = (string) (($zohoInvAfter['invoice'] ?? $zohoInvAfter)['balance'] ?? 'n/a');
            $results['zoho_invoice_balance_after_reversal'] = $balAfter;
            $results['invoice_restored'] = bccomp($balAfter, $amount, 4) === 0;

            $results['audit_logged'] = AuditLog::query()
                ->whereIn('action', [
                    'payment.confirmed',
                    'payment.zoho_synced',
                    'payment.zoho_sync_idempotent',
                    'payment.zoho_voided',
                    'payment.reversal_approved',
                ])
                ->where('created_at', '>=', now()->subHour())
                ->exists();

            $failed = collect($results)->filter(function ($value, $key) {
                if (in_array($key, [
                    'zoho_contact_id', 'zoho_invoice_id', 'invoice_number', 'local_customer_id',
                    'local_invoice_id', 'payment_uuid', 'payment_reference', 'zoho_payment_id',
                    'zoho_sync_status', 'reconciliation', 'zoho_invoice_balance',
                    'zoho_invoice_balance_after_reversal',
                ], true)) {
                    return false;
                }

                return $value !== true;
            })->keys();

            $this->newLine();
            $this->info('=== Stage 4 live verification report ===');
            foreach ($results as $key => $value) {
                $display = is_bool($value) ? ($value ? 'OK' : 'FAIL') : (string) $value;
                $this->line($key.': '.$display);
            }

            if ($failed->isNotEmpty()) {
                $this->error('LIVE VERIFY FAILED: '.$failed->implode(', '));

                return self::FAILURE;
            }

            $this->info('Stage 4 live Zoho verification PASSED.');
            $this->warn('Local labeled records retained for audit (payment not deleted).');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Live verify exception: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function ensureCashModeMapping(): void
    {
        $cash = PaymentMethod::query()->where('code', 'cash')->firstOrFail();
        ZohoPaymentModeMapping::query()->updateOrCreate(
            ['local_method' => 'cash'],
            [
                'name' => 'Cash',
                'zoho_payment_mode_id' => '303766000000013003',
                'payment_method_id' => $cash->id,
                'is_active' => true,
            ]
        );
    }

    protected function ensureDepositAccount(ZohoApiClient $zoho): void
    {
        $configured = trim((string) config('zoho.payments.default_account_id', ''));
        if ($configured !== '') {
            $this->line('Using configured deposit account '.$configured);

            return;
        }

        $name = (string) config('zoho.payments.default_account_name', 'Undeposited Funds');
        $response = $zoho->get('chartofaccounts', ['per_page' => 200], 'account');
        foreach ($response['chartofaccounts'] ?? [] as $account) {
            if (($account['account_name'] ?? '') === $name) {
                config(['zoho.payments.default_account_id' => (string) $account['account_id']]);
                $this->line("Resolved deposit account {$name} => {$account['account_id']}");

                return;
            }
        }

        throw new \RuntimeException("Unable to resolve Zoho deposit account [{$name}]. Set ZOHO_PAYMENT_ACCOUNT_ID.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function ensureZohoTestContact(ZohoApiClient $zoho): array
    {
        $name = 'STAGE5 TEST CUSTOMER - DO NOT USE';
        $search = $zoho->get('contacts', [
            'contact_name' => $name,
            'per_page' => 10,
        ], 'customer');

        foreach ($search['contacts'] ?? [] as $contact) {
            if (($contact['contact_name'] ?? '') === $name) {
                return $contact;
            }
        }

        $created = $zoho->post('contacts', [
            'contact_name' => $name,
            'company_name' => 'STAGE5 TEST — DO NOT USE',
            'contact_type' => 'customer',
            'customer_sub_type' => 'business',
            'notes' => 'Created for Stage 4 live Zoho payment verification. Do not invoice real services.',
        ], 'customer');

        return $created['contact'] ?? $created;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createZohoTestInvoice(ZohoApiClient $zoho, string $contactId, string $amount): array
    {
        $created = $zoho->post('invoices', [
            'customer_id' => $contactId,
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'reference_number' => 'STAGE4-LIVE-'.now()->format('YmdHis'),
            'notes' => 'STAGE4 LIVE TEST INVOICE — DO NOT USE',
            'line_items' => [
                [
                    'name' => 'STAGE4 LIVE TEST LINE — DO NOT USE',
                    'description' => 'Controlled Stage 4 live Zoho verification line',
                    'rate' => (float) $amount,
                    'quantity' => 1,
                ],
            ],
        ], 'invoice');

        $invoice = $created['invoice'] ?? $created;
        $invoiceId = (string) ($invoice['invoice_id'] ?? '');
        if ($invoiceId === '') {
            throw new \RuntimeException('Zoho invoice create missing invoice_id.');
        }

        // Ensure open/sent status so payment can be applied.
        try {
            $zoho->post('invoices/'.$invoiceId.'/status/sent', [], 'invoice');
        } catch (Throwable $e) {
            $this->warn('Mark invoice sent: '.$e->getMessage());
        }

        $fetched = $zoho->get('invoices/'.$invoiceId, [], 'invoice');

        return $fetched['invoice'] ?? $invoice;
    }

    protected function readPassword(): ?string
    {
        $path = $this->option('password-file');
        if (! $path || ! is_readable($path)) {
            $this->error('--password-file is required and must be readable.');

            return null;
        }
        $password = trim((string) file_get_contents($path));
        if ($password === '') {
            $this->error('Password file is empty.');

            return null;
        }

        return $password;
    }

    /**
     * @param  list<int>  $branchIds
     */
    protected function upsertUser(
        string $email,
        string $username,
        string $name,
        string $role,
        string $password,
        array $branchIds,
    ): User {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'status' => User::STATUS_ACTIVE,
                'force_password_change' => false,
            ]
        );
        $user->syncRoles([$role]);
        $user->branches()->sync($branchIds);

        return $user;
    }

    /**
     * @return array{0:User,1:Collector}
     */
    protected function upsertCollector(
        string $email,
        string $username,
        string $name,
        string $password,
        Branch $branch,
    ): array {
        $user = $this->upsertUser($email, $username, $name, User::ROLE_COLLECTOR, $password, [$branch->id]);
        $collector = Collector::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_code' => 'STAGE4-LIVE-COL',
                'is_active' => true,
                'max_active_assignments' => 50,
            ]
        );

        return [$user, $collector];
    }
}
