<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Inventory\Location;
use Illuminate\Database\Seeder;

class Stage9DefaultLocationsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['code' => 'MAIN-WH', 'type' => Location::TYPE_WAREHOUSE, 'name' => 'Main Warehouse', 'allow_receiving' => true, 'allow_dispatch' => true],
            ['code' => 'SALES', 'type' => Location::TYPE_SALES_STORE, 'name' => 'Sales Store', 'allow_sales' => true],
            ['code' => 'TECH', 'type' => Location::TYPE_TECHNICAL_STORE, 'name' => 'Technical Store', 'allow_dispatch' => true],
            ['code' => 'REPAIR', 'type' => Location::TYPE_REPAIR_STORE, 'name' => 'Repair Store'],
            ['code' => 'OFFICE', 'type' => Location::TYPE_OFFICE, 'name' => 'Office'],
            ['code' => 'TRANSIT', 'type' => Location::TYPE_IN_TRANSIT, 'name' => 'In Transit'],
            ['code' => 'DAMAGED', 'type' => Location::TYPE_DAMAGED, 'name' => 'Damaged'],
            ['code' => 'QUARANTINE', 'type' => Location::TYPE_QUARANTINE, 'name' => 'Quarantine'],
            ['code' => 'SCRAP', 'type' => Location::TYPE_SCRAP, 'name' => 'Scrap'],
        ];

        Branch::query()->each(function (Branch $branch) use ($defaults) {
            foreach ($defaults as $loc) {
                Location::withoutGlobalScopes()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'code' => $loc['code'],
                    ],
                    [
                        'type' => $loc['type'],
                        'name' => $loc['name'],
                        'allow_sales' => $loc['allow_sales'] ?? false,
                        'allow_receiving' => $loc['allow_receiving'] ?? false,
                        'allow_dispatch' => $loc['allow_dispatch'] ?? false,
                        'allow_negative' => false,
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}
