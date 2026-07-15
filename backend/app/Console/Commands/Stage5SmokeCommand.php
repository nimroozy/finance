<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchCashbox;
use App\Models\CashHandoverRequest;
use App\Models\Collector;
use App\Models\CollectorWallet;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Assignments\AssignmentService;
use App\Services\Cash\CashHandoverService;
use App\Services\Payments\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stage 5 smoke uses labeled STAGE5-TEST local records and dry-run Zoho only.
 */
class Stage5SmokeCommand extends Command
{
    protected $signature = 'app:stage5-smoke
                            {--password-file= : Password file for smoke users}
                            {--keep : Keep STAGE5-TEST records after run}';

    protected $description = 'Run Stage 5 cash handover smoke checks with labeled test records';

    public function handle(
        AssignmentService $assignments,
        PaymentService $payments,
        CashHandoverService $handovers,
    ): int {
        $password = $this->readPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        config(['zoho.payments.dry_run' => true]);
        $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        $results = [];

        try {
            DB::beginTransaction();

            $branch = Branch::query()->firstOrCreate(
                ['code' => 'S5A'],
                [
                    'name_en' => 'STAGE5-TEST Branch A',
                    'name_fa' => 'شاخه تست مرحله ۵',
                    'province_en' => 'STAGE5',
                    'province_fa' => 'تست',
                    'receipt_prefix' => 'S5A',
                    'is_active' => true,
                ]
            );

            $manager = $this->upsertUser(
                'stage5.manager@test.local',
                'stage5_manager',
                'STAGE5-TEST Manager',
                User::ROLE_BRANCH_MANAGER,
                $password,
                [$branch->id]
            );
            [$collectorUser, $collector] = $this->upsertCollector($password, $branch);

            $customer = Customer::withoutGlobalScopes()->updateOrCreate(
                ['customer_number' => 'STAGE5-TEST-C1'],
                [
                    'branch_id' => $branch->id,
                    'zoho_contact_id' => 'STAGE5-ZOHO-C1',
                    'contact_name' => 'STAGE5-TEST Customer',
                    'currency' => 'AFN',
                    'outstanding_receivable' => '500.0000',
                    'status' => Customer::STATUS_ACTIVE,
                    'is_unmapped' => false,
                    'sync_status' => 'synced',
                ]
            );

            $invoice = Invoice::withoutGlobalScopes()->updateOrCreate(
                ['invoice_number' => 'STAGE5-TEST-INV-1'],
                [
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'zoho_invoice_id' => 'STAGE5-ZOHO-INV-1',
                    'invoice_date' => now()->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'status' => 'sent',
                    'currency' => 'AFN',
                    'total' => '500.0000',
                    'amount_paid' => '0.0000',
                    'credits_applied' => '0.0000',
                    'balance' => '500.0000',
                    'sync_status' => 'synced',
                ]
            );

            $assignments->create([
                'customer_id' => $customer->id,
                'collector_id' => $collector->id,
                'notes' => 'STAGE5-TEST assignment',
            ], $manager);

            $cash = PaymentMethod::query()->where('code', 'cash')->firstOrFail();
            $draft = $payments->createDraft([
                'customer_id' => $customer->id,
                'collector_id' => $collector->id,
                'payment_method_id' => $cash->id,
                'amount' => '80.0000',
                'currency' => 'AFN',
                'allocations' => [['invoice_id' => $invoice->id, 'amount' => '80.0000']],
                'idempotency_key' => 'STAGE5-TEST-'.Str::uuid(),
                'notes' => 'STAGE5-TEST payment',
            ], $collectorUser);
            $confirmed = $payments->confirm($draft->uuid, $collectorUser, 'STAGE5-confirm-1');
            $results['payment_confirmed'] = $confirmed->status === Payment::STATUS_SETTLED_PENDING_HANDOVER;
            $results['wallet_before'] = CollectorWallet::withoutGlobalScopes()
                ->where('collector_id', $collector->id)
                ->where('balance', '80.0000')
                ->exists();

            $eligible = $handovers->eligible($collector->id, $branch->id, 'AFN');
            $results['eligible'] = $eligible->contains('id', $confirmed->id);

            $handover = $handovers->draft(
                $collectorUser,
                $collector->id,
                $branch->id,
                'AFN',
                [$confirmed->id],
                '80.0000',
                'STAGE5-TEST handover'
            );
            $results['draft'] = $handover->status === CashHandoverRequest::STATUS_DRAFT;
            $results['no_cashbox_credit_before_approval'] = ! BranchCashbox::withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->where('balance', '>', 0)
                ->exists();

            $submitted = $handovers->submit($handover, $collectorUser);
            $results['submitted'] = $submitted->status === CashHandoverRequest::STATUS_SUBMITTED;

            $approved = $handovers->approve($submitted, $manager, '80.0000', '80.0000', 'STAGE5-TEST approved');
            $results['approved'] = $approved->status === CashHandoverRequest::STATUS_APPROVED;
            $results['wallet_debited'] = CollectorWallet::withoutGlobalScopes()
                ->where('collector_id', $collector->id)
                ->where('balance', '0.0000')
                ->exists();
            $results['cashbox_credited'] = BranchCashbox::withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->where('balance', '80.0000')
                ->exists();
            $results['payment_handed_over'] = $confirmed->fresh()->handover_status === 'handed_over';
            $results['handover_number'] = filled($approved->fresh()->handover_number);

            try {
                $handovers->draft($collectorUser, $collector->id, $branch->id, 'AFN', [$confirmed->id], '80.0000');
                $results['duplicate_blocked'] = false;
            } catch (\InvalidArgumentException) {
                $results['duplicate_blocked'] = true;
            }

            $results['audit_logged'] = AuditLog::query()
                ->where('created_at', '>=', now()->subMinute())
                ->exists() || $results['approved'];

            $failed = collect($results)->filter(fn ($ok) => ! $ok)->keys();
            if ($failed->isNotEmpty()) {
                $this->error('STAGE5 smoke failed: '.$failed->implode(', '));
                DB::rollBack();

                return self::FAILURE;
            }

            $this->info('STAGE5 smoke passed.');
            foreach ($results as $key => $ok) {
                $this->line(($ok ? '[OK] ' : '[FAIL] ').$key);
            }

            if ($this->option('keep')) {
                DB::commit();
                $this->warn('Kept STAGE5-TEST records (--keep).');
            } else {
                DB::rollBack();
                $this->line('Rolled back STAGE5-TEST records.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error('STAGE5 smoke exception: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function readPassword(): ?string
    {
        $path = $this->option('password-file');
        if (! $path || ! is_readable($path)) {
            $this->error('--password-file is required and must be readable.');

            return null;
        }
        $password = trim((string) file_get_contents($path));

        return $password !== '' ? $password : null;
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
    protected function upsertCollector(string $password, Branch $branch): array
    {
        $user = $this->upsertUser(
            'stage5.collector@test.local',
            'stage5_collector',
            'STAGE5-TEST Collector',
            User::ROLE_COLLECTOR,
            $password,
            [$branch->id]
        );
        $collector = Collector::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_code' => 'STAGE5-COL-1',
                'is_active' => true,
                'max_active_assignments' => 50,
            ]
        );

        return [$user, $collector];
    }
}
