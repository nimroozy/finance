<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

trait CreatesCollectionFixtures
{
    protected Branch $branch;

    protected function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeBranch(array $attributes = []): Branch
    {
        return Branch::factory()->create($attributes);
    }

    protected function makeManager(Branch $branch): User
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_BRANCH_MANAGER);
        $user->branches()->attach($branch->id);

        return $user;
    }

    protected function makeCollectorUser(Branch $branch, array $collectorAttrs = []): array
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_COLLECTOR);
        $user->branches()->attach($branch->id);
        $collector = Collector::factory()->create(array_merge([
            'user_id' => $user->id,
        ], $collectorAttrs));

        return [$user, $collector];
    }

    protected function makeCustomer(Branch $branch, array $attributes = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'branch_id' => $branch->id,
            'is_unmapped' => false,
            'status' => Customer::STATUS_ACTIVE,
            'outstanding_receivable' => '1500.0000',
        ], $attributes));
    }

    protected function actingAsUser(User $user): User
    {
        Sanctum::actingAs($user);

        return $user;
    }
}
