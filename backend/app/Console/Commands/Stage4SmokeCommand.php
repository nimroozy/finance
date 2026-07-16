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
use App\Services\Assignments\AssignmentService;
use App\Services\Payments\PaymentReversalService;
use App\Services\Payments\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stage 4 smoke with clearly labeled STAGE4-TEST records. Zoho is dry-run/mocked.
 */
class Stage4SmokeCommand extends Command
{
    protected $signature = 'app:stage4-smoke
                            {--password-file= : Password file for smoke users (required)}
                            {--dry-run-zoho : Force Zoho payment dry-run mode}
                            {--keep : Keep STAGE4-TEST records after run}';

    protected $description = 'Run Stage 4 payment smoke checks with labeled test records';

    public function handle(
        AssignmentService $assignments,
        PaymentService $payments,
        PaymentReversalService $reversals,
    ): int {
        $password = $this->readPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        if ($this->option('dry-run-zoho')) {
            config(['zoho.payments.dry_run' => true]);
        }

        $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        $results = [];

        try {
            DB::beginTransaction();

            $branch = Branch::query()->firstOrCreate(
                ['code' => 'S4A'],
                [
                    'name_en' => 'STAGE4-TEST Branch A',
                    'name_fa' => 'شاخه تست مرحله ۴',
                    'province_en' => 'STAGE4',
                    'province_fa' => 'تست',
                    'receipt_prefix' => 'S4A',
                    'is_active' => true,
                ]
            );

            $manager = $this->upsertSmokeUser(
                'stage4.manager@test.local',
                'stage4_manager',
                'STAGE4-TEST Manager',
                User::ROLE_BRANCH_MANAGER,
                $password,
                [$branch->id]
            );

            [$collectorUser, $collector] = $this->upsertCollector(
                'stage4.collector@test.local',
                'stage4_collector',
                'STAGE4-TEST Collector',
                $password,
                $branch
            );

            $customer = Customer::withoutGlobalScopes()->updateOrCreate(
                ['customer_number' => 'STAGE4-TEST-C1'],
                [
                    'branch_id' => $branch->id,
                    'zoho_contact_id' => 'STAGE4-ZOHO-C1',
                    'contact_name' => 'STAGE4-TEST Customer',
                    'currency' => 'AFN',
                    'outstanding_receivable' => '1000.0000',
                    'status' => Customer::STATUS_ACTIVE,
                    'is_unmapped' => false,
                    'sync_status' => 'synced',
                ]
            );

            $invoice = Invoice::withoutGlobalScopes()->updateOrCreate(
                ['invoice_number' => 'STAGE4-TEST-INV-1'],
                [
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'zoho_invoice_id' => 'STAGE4-ZOHO-INV-1',
                    'invoice_date' => now()->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'status' => 'sent',
                    'currency' => 'AFN',
                    'total' => '1000.0000',
                    'amount_paid' => '0.0000',
                    'credits_applied' => '0.0000',
                    'balance' => '1000.0000',
                    'sync_status' => 'synced',
                ]
            );

            $assignment = $assignments->create([
                'customer_id' => $customer->id,
                'collector_id' => $collector->id,
                'notes' => 'STAGE4-TEST assignment',
            ], $manager);
            $results['assignment_created'] = $assignment->id > 0;

            $cash = PaymentMethod::query()->where('code', 'cash')->firstOrFail();

            $draft = $payments->createDraft([
                'customer_id' => $customer->id,
                'collector_id' => $collector->id,
                'payment_method_id' => $cash->id,
                'amount' => '125.0000',
                'currency' => 'AFN',
                'allocations' => [
                    ['invoice_id' => $invoice->id, 'amount' => '125.0000'],
                ],
                'idempotency_key' => 'STAGE4-TEST-'.Str::uuid(),
                'notes' => 'STAGE4-TEST payment',
            ], $collectorUser);
            $results['draft_created'] = $draft->status === Payment::STATUS_DRAFT;

            $confirmed = $payments->confirm($draft->uuid, $collectorUser, 'STAGE4-confirm-1');
            $results['payment_confirmed'] = in_array($confirmed->status, Payment::CONFIRMED_STATUSES, true);
            $results['receipt_issued'] = Receipt::withoutGlobalScopes()->where('payment_id', $confirmed->id)->exists();
            $results['wallet_credited'] = DB::table('collector_wallets')
                ->where('collector_id', $collector->id)
                ->where('balance', '125.0000')
                ->exists();
            $results['zoho_sync_attempted'] = in_array($confirmed->fresh()->zoho_sync_status, [
                Payment::ZOHO_DRY_RUN,
                Payment::ZOHO_SYNCED,
                Payment::ZOHO_FAILED,
                Payment::ZOHO_PENDING,
            ], true);

            $reversal = $reversals->request($confirmed->fresh(), $collectorUser, 'STAGE4-TEST reversal');
            $approved = $reversals->approve($reversal, $manager);
            $results['reversal_approved'] = $approved->status === PaymentReversal::STATUS_APPROVED
                && $confirmed->fresh()->status === Payment::STATUS_REVERSED;

            $results['audit_logged'] = AuditLog::query()
                ->whereIn('action', ['payment.confirmed', 'receipt.issued', 'payment.reversal_approved'])
                ->where('created_at', '>=', now()->subMinute())
                ->exists();

            $failed = collect($results)->filter(fn ($ok) => ! $ok)->keys();
            if ($failed->isNotEmpty()) {
                $this->error('STAGE4 smoke failed: '.$failed->implode(', '));
                DB::rollBack();

                return self::FAILURE;
            }

            $this->info('STAGE4 smoke passed.');
            foreach ($results as $key => $ok) {
                $this->line(($ok ? '[OK] ' : '[FAIL] ').$key);
            }

            if ($this->option('keep')) {
                DB::commit();
                $this->warn('Kept STAGE4-TEST records (--keep).');
            } else {
                DB::rollBack();
                $this->line('Rolled back STAGE4-TEST records.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('STAGE4 smoke exception: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function readPassword(): ?string
    {
        $path = $this->option('password-file');
        if (! $path) {
            $this->error('--password-file is required.');

            return null;
        }
        if (! is_readable($path)) {
            $this->error('Password file not readable.');

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
    protected function upsertSmokeUser(
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
        $user = $this->upsertSmokeUser($email, $username, $name, User::ROLE_COLLECTOR, $password, [$branch->id]);
        $collector = Collector::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_code' => 'STAGE4-COL-1',
                'is_active' => true,
                'max_active_assignments' => 50,
            ]
        );

        return [$user, $collector];
    }
}
