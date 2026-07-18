<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            BranchCustomerPrefixSeeder::class,
            WhatsAppNotificationRuleSeeder::class,
            Stage7OrgSeeder::class,
            Stage7SlaPolicySeeder::class,
            Stage7TicketTypeSeeder::class,
            Stage7TaskTemplateSeeder::class,
            Stage7EscalationRuleSeeder::class,
        ]);
    }
}
