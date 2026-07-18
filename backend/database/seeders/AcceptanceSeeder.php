<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\Services\Service;
use App\Models\Services\ServiceCancellation;
use App\Models\Services\ServiceChangeRequest;
use App\Models\Services\ServiceFinanceHold;
use App\Models\Services\ServiceLocation;
use App\Models\Services\ServicePackage;
use App\Models\Services\ServiceRelocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic fixtures for Stage 10.3 functional acceptance / real E2E.
 *
 * Users use ACCEPTANCE- usernames. Idempotent via updateOrCreate.
 *
 * Run: php artisan db:seed --class=AcceptanceSeeder
 */
class AcceptanceSeeder extends Seeder
{
    public const PASSWORD = 'AcceptancePass1!';

    public const BRANCH_CODE = 'ACC';

    public const ZOHO_CONTACT_ID = 'ACCEPTANCE-ZOHO-1';

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            Stage8LeadSourceSeeder::class,
            Stage10ServiceTypeSeeder::class,
            Stage10AccessTechnologySeeder::class,
            Stage10SlaTemplateSeeder::class,
        ]);

        $branch = Branch::query()->withoutGlobalScopes()->updateOrCreate(
            ['code' => self::BRANCH_CODE],
            [
                'name_en' => 'Acceptance Branch',
                'name_fa' => 'شعبه پذیرش',
                'province_en' => 'Kabul',
                'province_fa' => 'کابل',
                'receipt_prefix' => 'ACC',
                'is_active' => true,
            ]
        );

        $admin = $this->seedUser('ACCEPTANCE-admin', 'ACCEPTANCE Admin', User::ROLE_SUPER_ADMIN, $branch);
        $manager = $this->seedUser('ACCEPTANCE-manager', 'ACCEPTANCE Manager', User::ROLE_BRANCH_MANAGER, $branch);
        $this->seedUser('ACCEPTANCE-sales', 'ACCEPTANCE Sales', User::ROLE_BRANCH_MANAGER, $branch);
        $this->seedUser('ACCEPTANCE-noc', 'ACCEPTANCE NOC', User::ROLE_BRANCH_MANAGER, $branch);

        $customer = Customer::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'zoho_contact_id' => self::ZOHO_CONTACT_ID,
            ],
            [
                'contact_name' => 'ACCEPTANCE Zoho Customer',
                'customer_number' => 'ACC-CUST-001',
                'phone' => '0700111222',
                'email' => 'acceptance-customer@example.test',
                'status' => Customer::STATUS_ACTIVE,
                'is_unmapped' => false,
                'sync_status' => 'synced',
                'outstanding_receivable' => '0.0000',
            ]
        );

        Lead::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'lead_number' => 'ACC-LEAD-000001',
            ],
            [
                'contact_person' => 'ACCEPTANCE Lead',
                'company' => 'ACCEPTANCE Co',
                'phone' => '0700111222',
                'email' => 'acceptance-lead@example.test',
                'address' => 'Acceptance Street 1',
                'source_code' => 'other',
                'pipeline_stage' => Lead::STAGE_APPROVED,
                'owner_id' => $manager->id,
                'created_by' => $manager->id,
                'notes' => 'Acceptance fixture — link to Zoho-mirrored customer '.$customer->id,
            ]
        );

        $package = ServicePackage::query()->updateOrCreate(
            ['code' => 'ACC-PKG-20'],
            [
                'name' => 'Acceptance 20Mbps',
                'branch_id' => $branch->id,
                'service_type_code' => 'internet',
                'access_technology_code' => 'ftth',
                'download_mbps' => 20,
                'upload_mbps' => 20,
                'monthly_price' => 1500,
                'installation_fee' => 500,
                'is_active' => true,
            ]
        );

        $location = ServiceLocation::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'name' => 'ACCEPTANCE Primary Site',
            ],
            [
                'address' => 'Acceptance Street 1',
                'city' => 'Kabul',
            ]
        );

        $altLocation = ServiceLocation::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'name' => 'ACCEPTANCE Relocation Site',
            ],
            [
                'address' => 'Acceptance Street 2',
                'city' => 'Kabul',
            ]
        );

        $service = Service::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'service_number' => 'ACC-SVC-000001',
            ],
            [
                'customer_id' => $customer->id,
                'zoho_customer_id' => self::ZOHO_CONTACT_ID,
                'service_location_id' => $location->id,
                'package_id' => $package->id,
                'service_type_code' => 'internet',
                'access_technology_code' => 'ftth',
                'mrr' => 1500,
                'commercial_status' => Service::COMMERCIAL_ACTIVE,
                'operational_status' => Service::OPERATIONAL_ONLINE,
                'billing_status' => Service::BILLING_NOT_BILLED,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );

        Service::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'service_number' => 'ACC-SVC-PENDING',
            ],
            [
                'customer_id' => $customer->id,
                'zoho_customer_id' => null,
                'service_location_id' => $location->id,
                'package_id' => $package->id,
                'service_type_code' => 'internet',
                'access_technology_code' => 'ftth',
                'mrr' => 1500,
                'commercial_status' => Service::COMMERCIAL_PENDING_ACTIVATION,
                'operational_status' => Service::OPERATIONAL_READY,
                'billing_status' => Service::BILLING_NOT_BILLED,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );

        ServiceChangeRequest::query()->updateOrCreate(
            [
                'service_id' => $service->id,
                'reason' => 'ACCEPTANCE change request fixture',
            ],
            [
                'type' => 'package_change',
                'current_values' => ['package_id' => $package->id],
                'requested_values' => ['package_id' => $package->id],
                'requested_by' => $manager->id,
                'status' => ServiceChangeRequest::STATUS_REQUESTED,
                'notes' => 'Acceptance queue fixture',
            ]
        );

        ServiceRelocation::query()->updateOrCreate(
            [
                'service_id' => $service->id,
                'notes' => 'ACCEPTANCE relocation fixture',
            ],
            [
                'status' => ServiceRelocation::STATUS_REQUESTED,
                'old_location_id' => $location->id,
                'new_location_id' => $altLocation->id,
            ]
        );

        ServiceFinanceHold::query()->updateOrCreate(
            [
                'service_id' => $service->id,
                'reason' => 'ACCEPTANCE finance hold fixture',
            ],
            [
                'hold_type' => 'manual',
                'status' => ServiceFinanceHold::STATUS_ACTIVE,
                'effective_at' => now(),
                'approved_by' => $manager->id,
            ]
        );

        $cancelService = Service::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'service_number' => 'ACC-SVC-CANCEL',
            ],
            [
                'customer_id' => $customer->id,
                'zoho_customer_id' => self::ZOHO_CONTACT_ID,
                'service_location_id' => $location->id,
                'package_id' => $package->id,
                'service_type_code' => 'internet',
                'access_technology_code' => 'ftth',
                'mrr' => 1500,
                'commercial_status' => Service::COMMERCIAL_PENDING_CANCELLATION,
                'operational_status' => Service::OPERATIONAL_ONLINE,
                'billing_status' => Service::BILLING_NOT_BILLED,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );

        ServiceCancellation::query()->updateOrCreate(
            [
                'service_id' => $cancelService->id,
                'reason' => 'ACCEPTANCE cancellation fixture',
            ],
            [
                'requested_by' => $manager->id,
                'equipment_return_required' => true,
                'status' => ServiceCancellation::STATUS_REQUESTED,
                'notes' => 'Acceptance cancellations queue fixture',
            ]
        );
    }

    private function seedUser(string $username, string $name, string $role, Branch $branch): User
    {
        $user = User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => strtolower($username).'@example.test',
                'password' => Hash::make(self::PASSWORD),
                'locale' => 'en',
                'status' => User::STATUS_ACTIVE,
                'force_password_change' => false,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]
        );

        $user->syncRoles([$role]);
        $user->branches()->syncWithoutDetaching([$branch->id]);

        return $user;
    }
}
