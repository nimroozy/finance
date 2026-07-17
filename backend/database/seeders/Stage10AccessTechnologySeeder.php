<?php

namespace Database\Seeders;

use App\Models\Services\ServiceAccessTechnology;
use Illuminate\Database\Seeder;

class Stage10AccessTechnologySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'ftth', 'name_en' => 'FTTH', 'name_fa' => 'فیبر تا منزل'],
            ['code' => 'wireless', 'name_en' => 'Wireless', 'name_fa' => 'بی‌سیم'],
            ['code' => 'microwave', 'name_en' => 'Microwave', 'name_fa' => 'مایکروویو'],
            ['code' => 'dsl', 'name_en' => 'DSL', 'name_fa' => 'دی‌اس‌ال'],
            ['code' => 'ethernet', 'name_en' => 'Ethernet', 'name_fa' => 'اترنت'],
        ];

        foreach ($items as $item) {
            ServiceAccessTechnology::query()->updateOrCreate(
                ['code' => $item['code']],
                [...$item, 'is_active' => true],
            );
        }
    }
}
