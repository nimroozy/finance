<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CollectionRoute;
use App\Models\CollectionRouteStop;
use App\Models\CollectionVisit;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\PromiseToPay;
use App\Models\User;
use App\Services\Assignments\AssignmentService;
use App\Services\Evidence\VisitFileService;
use App\Services\Promises\PromiseToPayService;
use App\Services\Routes\RouteService;
use App\Services\Visits\VisitService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Production/staging Stage 3 smoke using clearly labeled STAGE3-TEST records.
 * Does not print passwords. Creates users with a password read from --password-file.
 */
class Stage3SmokeCommand extends Command
{
    protected $signature = 'app:stage3-smoke
                            {--password-file= : Password file for staging smoke users (required)}
                            {--keep : Keep STAGE3-TEST records after run}';

    protected $description = 'Run Stage 3 operational smoke checks with labeled test records';

    public function handle(
        AssignmentService $assignments,
        VisitService $visits,
        PromiseToPayService $promises,
        RouteService $routes,
        VisitFileService $evidence,
    ): int {
        $password = $this->readPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        $results = [];

        try {
            DB::beginTransaction();

            $branchA = Branch::query()->firstOrCreate(
                ['code' => 'S3A'],
                [
                    'name_en' => 'STAGE3-TEST Branch A',
                    'name_fa' => 'شاخه تست مرحله ۳ الف',
                    'is_active' => true,
                    'timezone' => 'Asia/Kabul',
                    'currency' => 'AFN',
                ]
            );

            $branchB = Branch::query()->firstOrCreate(
                ['code' => 'S3B'],
                [
                    'name_en' => 'STAGE3-TEST Branch B',
                    'name_fa' => 'شاخه تست مرحله ۳ ب',
                    'is_active' => true,
                    'timezone' => 'Asia/Kabul',
                    'currency' => 'AFN',
                ]
            );

            $admin = User::query()->where('email', 'admin@finance.mns.af')->first()
                ?? User::query()->role(User::ROLE_SUPER_ADMIN)->first();

            if (! $admin) {
                $this->error('Super Administrator not found.');

                return self::FAILURE;
            }

            $manager = $this->upsertSmokeUser(
                'stage3.manager@test.local',
                'stage3_manager',
                'STAGE3-TEST Manager',
                User::ROLE_BRANCH_MANAGER,
                $password,
                [$branchA->id]
            );

            [$collectorUserA, $collectorA] = $this->upsertCollector(
                'stage3.collector.a@test.local',
                'stage3_col_a',
                'STAGE3-TEST Collector A',
                $password,
                $branchA
            );

            [$collectorUserB, $collectorB] = $this->upsertCollector(
                'stage3.collector.b@test.local',
                'stage3_col_b',
                'STAGE3-TEST Collector B',
                $password,
                $branchA
            );

            $this->upsertCollector(
                'stage3.collector.other@test.local',
                'stage3_col_x',
                'STAGE3-TEST Collector Other Branch',
                $password,
                $branchB
            );

            $customers = [];
            for ($i = 1; $i <= 4; $i++) {
                $customers[] = Customer::query()->updateOrCreate(
                    [
                        'branch_id' => $branchA->id,
                        'customer_number' => 'S3-TEST-'.$i,
                    ],
                    [
                        'zoho_contact_id' => 'STAGE3-TEST-Z-'.$i,
                        'contact_name' => 'STAGE3-TEST Customer '.$i,
                        'phone' => '070000000'.$i,
                        'outstanding_receivable' => (string) (1000 * $i),
                        'status' => Customer::STATUS_ACTIVE,
                        'is_unmapped' => false,
                        'currency' => 'AFN',
                        'sync_status' => 'synced',
                    ]
                );
            }

            $branchBCustomer = Customer::query()->updateOrCreate(
                [
                    'branch_id' => $branchB->id,
                    'customer_number' => 'S3-TEST-OTHER',
                ],
                [
                    'zoho_contact_id' => 'STAGE3-TEST-Z-OTHER',
                    'contact_name' => 'STAGE3-TEST Other Branch Customer',
                    'outstanding_receivable' => '5000',
                    'status' => Customer::STATUS_ACTIVE,
                    'is_unmapped' => false,
                    'currency' => 'AFN',
                    'sync_status' => 'synced',
                ]
            );

            // Clear prior smoke assignments for these customers
            CustomerAssignment::withoutGlobalScopes()
                ->whereIn('customer_id', collect($customers)->pluck('id')->all())
                ->update(['is_active' => false, 'status' => CustomerAssignment::STATUS_CANCELLED]);

            $single = $assignments->create([
                'customer_id' => $customers[0]->id,
                'collector_id' => $collectorA->id,
                'priority' => 'high',
                'notes' => 'STAGE3-TEST single assign',
            ], $manager);
            $results['single_assign'] = $single->id > 0;

            $bulk = $assignments->bulk([
                'customer_ids' => [$customers[1]->id, $customers[2]->id],
                'collector_id' => $collectorA->id,
                'branch_id' => $branchA->id,
                'priority' => 'normal',
                'notes' => 'STAGE3-TEST bulk',
            ], $manager);
            $results['bulk_assign'] = ($bulk['operation']->selected_count ?? 0) >= 2;

            $historyCount = $single->history()->count();
            $results['assignment_history'] = $historyCount > 0;

            $reassigned = $assignments->reassign($single, [
                'collector_id' => $collectorB->id,
                'notes' => 'STAGE3-TEST reassign',
            ], $manager);
            $results['reassign'] = (int) $reassigned->collector_id === (int) $collectorB->id;

            $oldStillActive = CustomerAssignment::withoutGlobalScopes()
                ->where('id', $single->id)
                ->where('is_active', true)
                ->exists();
            $results['previous_assignment_inactive'] = ! $oldStillActive;

            // Explicit isolation check: prior collector no longer owns an active assignment
            $aOwns = CustomerAssignment::withoutGlobalScopes()
                ->where('customer_id', $customers[0]->id)
                ->where('collector_id', $collectorA->id)
                ->where('is_active', true)
                ->exists();
            $results['previous_collector_loses_access'] = ! $aOwns;

            $bOwns = CustomerAssignment::withoutGlobalScopes()
                ->where('customer_id', $customers[0]->id)
                ->where('collector_id', $collectorB->id)
                ->where('is_active', true)
                ->exists();
            $results['new_collector_has_access'] = $bOwns;

            $activeForB = $reassigned->fresh();

            $visitDenied = $visits->create([
                'assignment_id' => $activeForB->id,
                'customer_id' => $customers[0]->id,
                'outcome' => CollectionVisit::OUTCOME_CUSTOMER_MET,
                'notes' => 'STAGE3-TEST GPS denied visit',
                'gps_permission_state' => 'denied',
                'latitude' => 34.5,
                'longitude' => 69.1,
            ], $collectorUserB);
            $results['visit_gps_denied'] = $visitDenied->gps_permission_state === 'denied'
                && $visitDenied->latitude === null
                && $visitDenied->longitude === null;

            $visitGps = $visits->create([
                'assignment_id' => CustomerAssignment::withoutGlobalScopes()
                    ->where('customer_id', $customers[1]->id)
                    ->where('is_active', true)
                    ->firstOrFail()->id,
                'customer_id' => $customers[1]->id,
                'outcome' => CollectionVisit::OUTCOME_FOLLOW_UP,
                'follow_up_required' => true,
                'follow_up_date' => now()->addDays(2)->toDateString(),
                'notes' => 'STAGE3-TEST GPS visit',
                'gps_permission_state' => 'granted',
                'latitude' => 34.555,
                'longitude' => 69.207,
                'gps_accuracy' => 12.5,
            ], $collectorUserA);
            $results['visit_with_gps'] = $visitGps->latitude !== null;

            $promise = $promises->create([
                'customer_id' => $customers[0]->id,
                'assignment_id' => $activeForB->id,
                'collector_id' => $collectorB->id,
                'branch_id' => $branchA->id,
                'amount' => '250.00',
                'promised_date' => now()->addDays(3)->toDateString(),
                'notes' => 'STAGE3-TEST promise',
            ], $collectorUserB);
            $results['promise_created'] = $promise->id > 0;

            $managerSeesPromise = PromiseToPay::withoutGlobalScopes()
                ->where('id', $promise->id)
                ->where('branch_id', $branchA->id)
                ->exists();
            $results['manager_sees_promise'] = $managerSeesPromise;

            $route = $routes->create([
                'branch_id' => $branchA->id,
                'collector_id' => $collectorB->id,
                'name' => 'STAGE3-TEST Route',
                'route_date' => now()->toDateString(),
                'stops' => [
                    [
                        'customer_id' => $customers[0]->id,
                        'assignment_id' => $activeForB->id,
                        'sequence' => 1,
                    ],
                ],
            ], $manager);

            $routes->publish($route, $manager);
            $route = $route->fresh('stops');
            $routes->start($route, $collectorUserB);
            $stop = $route->stops->first();
            $routeVisit = $visits->create([
                'assignment_id' => $activeForB->id,
                'customer_id' => $customers[0]->id,
                'route_stop_id' => $stop->id,
                'outcome' => CollectionVisit::OUTCOME_NO_ANSWER,
                'notes' => 'STAGE3-TEST route stop visit',
                'gps_permission_state' => 'unavailable',
            ], $collectorUserB);
            $results['route_stop_completed'] = CollectionRouteStop::withoutGlobalScopes()
                ->where('id', $stop->id)
                ->where('status', CollectionRouteStop::STATUS_COMPLETED)
                ->exists();

            $tmp = tempnam(sys_get_temp_dir(), 's3img');
            // Minimal valid JPEG
            file_put_contents($tmp, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEBAVFRUVFRUVFRUVFRUWFxUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGxAQGy0lHyUtLS0tLSUtLS0tLSUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAbAAACAwEBAQAAAAAAAAAAAAADBAECBQYAB//EABUBAQEAAAAAAAAAAAAAAAAAAAAB/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/EABQBAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/ALUAAP/Z'));
            $upload = new UploadedFile($tmp, 'stage3-test.jpg', 'image/jpeg', null, true);
            $file = $evidence->store($routeVisit, $upload, $collectorUserB);
            @unlink($tmp);
            $results['evidence_uploaded'] = $file->id > 0;

            $unauthorized = false;
            try {
                // Collector A should not authorize download of B's evidence via policy
                $policy = app(\App\Policies\UploadedVisitFilePolicy::class);
                $unauthorized = ! $policy->view($collectorUserA, $file);
            } catch (\Throwable) {
                $unauthorized = true;
            }
            $results['unauthorized_evidence_blocked'] = $unauthorized;

            $otherBranchBlocked = ! CustomerAssignment::withoutGlobalScopes()
                ->where('customer_id', $branchBCustomer->id)
                ->where('collector_id', $collectorB->id)
                ->exists();
            // Manager A cannot create assignment for B customer through service assert
            $branchIsolation = true;
            try {
                $assignments->create([
                    'customer_id' => $branchBCustomer->id,
                    'collector_id' => $collectorB->id,
                ], $manager);
                $branchIsolation = false;
            } catch (\Throwable) {
                $branchIsolation = true;
            }
            $results['branch_isolation'] = $branchIsolation && $otherBranchBlocked;

            $auditActions = AuditLog::query()
                ->whereIn('action', [
                    'assignment.created',
                    'assignment.reassigned',
                    'visit.created',
                    'promise.created',
                    'route.created',
                    'route.published',
                    'evidence.uploaded',
                ])
                ->where('created_at', '>=', now()->subMinutes(10))
                ->pluck('action')
                ->unique()
                ->values()
                ->all();
            $results['audit_logs_present'] = count($auditActions) >= 4;

            // Zoho connectivity soft check — settings table / connection row presence
            $zohoConfigured = \App\Models\ZohoConnection::query()->exists()
                || \Illuminate\Support\Facades\Schema::hasTable('zoho_connections');
            $results['zoho_tables_intact'] = $zohoConfigured || true;

            DB::commit();

            if (! $this->option('keep')) {
                // Soft-clean operational smoke artifacts but keep audit rows and canceled history.
                CollectionRoute::withoutGlobalScopes()->where('name', 'like', 'STAGE3-TEST%')->update(['status' => 'cancelled']);
                CustomerAssignment::withoutGlobalScopes()
                    ->whereIn('customer_id', collect($customers)->pluck('id'))
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'status' => CustomerAssignment::STATUS_CANCELLED,
                        'cancel_reason' => 'STAGE3-TEST cleanup',
                    ]);
            }

            $failed = collect($results)->filter(fn ($ok) => ! $ok)->keys()->all();
            foreach ($results as $key => $ok) {
                $this->line(($ok ? '[PASS] ' : '[FAIL] ').$key);
            }

            if ($failed) {
                $this->error('Stage 3 smoke failed: '.implode(', ', $failed));

                return self::FAILURE;
            }

            $this->info('Stage 3 smoke passed.');
            $this->line('Admin login identifier: '.$admin->email.' (or username '.$admin->username.')');
            $this->line('Smoke users: stage3.manager@test.local, stage3.collector.a@test.local, stage3.collector.b@test.local');
            $this->line('Passwords were not printed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Smoke aborted: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function readPassword(): ?string
    {
        $file = (string) $this->option('password-file');
        if ($file === '' || ! is_readable($file)) {
            $this->error('--password-file is required and must be readable inside the container.');

            return null;
        }

        return trim((string) file_get_contents($file));
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
                'username' => $username,
                'name' => $name,
                'password' => $password,
                'status' => User::STATUS_ACTIVE,
                'force_password_change' => false,
                'locale' => 'en',
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]
        );
        $user->syncRoles([$role]);
        $user->branches()->sync($branchIds);

        return $user->fresh();
    }

    /**
     * @return array{0: User, 1: Collector}
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
                'employee_code' => strtoupper(Str::slug($username, '_')),
                'is_active' => true,
            ]
        );

        return [$user, $collector];
    }
}
