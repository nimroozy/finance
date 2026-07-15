<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

trait CreatesAuthenticatedUsers
{
    protected function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function actingAsRole(string $role, array $attributes = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    protected function createUserWithRole(string $role, array $attributes = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
