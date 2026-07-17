<?php

namespace Database\Seeders;

use App\Models\Services\ServiceType;
use Illuminate\Database\Seeder;

class Stage10ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'internet', 'name_en' => 'Internet', 'name_fa' => 'اینترنت'],
            ['code' => 'voip', 'name_en' => 'VoIP', 'name_fa' => 'ویپ'],
            ['code' => 'leased_line', 'name_en' => 'Leased Line', 'name_fa' => 'خطوط اختصاصی'],
            ['code' => 'dark_fiber', 'name_en' => 'Dark Fiber', 'name_fa' => 'فیبر تاریک'],
            ['code' => 'managed_wifi', 'name_en' => 'Managed WiFi', 'name_fa' => 'وای‌فای مدیریت‌شده'],
        ];

        foreach ($types as $type) {
            ServiceType::query()->updateOrCreate(
                ['code' => $type['code']],
                [...$type, 'is_active' => true],
            );
        }
    }
}
