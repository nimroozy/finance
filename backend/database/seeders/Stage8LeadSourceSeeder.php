<?php

namespace Database\Seeders;

use App\Models\Crm\LeadSource;
use Illuminate\Database\Seeder;

class Stage8LeadSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['code' => 'whatsapp', 'name_en' => 'WhatsApp', 'name_fa' => 'واتساپ'],
            ['code' => 'walk_in', 'name_en' => 'Walk-in', 'name_fa' => 'مراجعه حضوری'],
            ['code' => 'referral', 'name_en' => 'Referral', 'name_fa' => 'معرفی'],
            ['code' => 'campaign', 'name_en' => 'Campaign', 'name_fa' => 'کمپین'],
            ['code' => 'phone_inbound', 'name_en' => 'Phone Inbound', 'name_fa' => 'تماس ورودی'],
            ['code' => 'website', 'name_en' => 'Website', 'name_fa' => 'وب‌سایت'],
            ['code' => 'social_media', 'name_en' => 'Social Media', 'name_fa' => 'شبکه‌های اجتماعی'],
            ['code' => 'partner_dealer', 'name_en' => 'Partner / Dealer', 'name_fa' => 'شریک / نمایندگی'],
            ['code' => 'field_sales', 'name_en' => 'Field Sales', 'name_fa' => 'فروش میدانی'],
            ['code' => 'other', 'name_en' => 'Other', 'name_fa' => 'سایر'],
        ];

        foreach ($sources as $source) {
            LeadSource::query()->updateOrCreate(
                ['code' => $source['code']],
                [
                    'name_en' => $source['name_en'],
                    'name_fa' => $source['name_fa'],
                    'is_active' => true,
                ],
            );
        }
    }
}
