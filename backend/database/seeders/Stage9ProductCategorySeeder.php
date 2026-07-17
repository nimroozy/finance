<?php

namespace Database\Seeders;

use App\Models\Inventory\ProductCategory;
use Illuminate\Database\Seeder;

class Stage9ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'CPE', 'name_en' => 'Customer Premises Equipment', 'name_fa' => null],
            ['code' => 'RADIO', 'name_en' => 'Radio / Wireless', 'name_fa' => null],
            ['code' => 'ROUTER', 'name_en' => 'Routers & Switches', 'name_fa' => null],
            ['code' => 'POWER', 'name_en' => 'Power Systems', 'name_fa' => null],
            ['code' => 'CABLE', 'name_en' => 'Cables & Consumables', 'name_fa' => null],
            ['code' => 'TOWER', 'name_en' => 'Tower Infrastructure', 'name_fa' => null],
            ['code' => 'OFFICE', 'name_en' => 'Office Equipment', 'name_fa' => null],
            ['code' => 'TOOL', 'name_en' => 'Tools & Test Equipment', 'name_fa' => null],
            ['code' => 'SERVICE', 'name_en' => 'Non-Stock Services', 'name_fa' => null],
        ];

        foreach ($categories as $cat) {
            ProductCategory::query()->updateOrCreate(
                ['code' => $cat['code']],
                [
                    'name_en' => $cat['name_en'],
                    'name_fa' => $cat['name_fa'],
                    'is_active' => true,
                ]
            );
        }
    }
}
