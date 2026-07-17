<?php

namespace Database\Seeders;

use App\Models\Services\ServiceSlaTemplate;
use Illuminate\Database\Seeder;

class Stage10SlaTemplateSeeder extends Seeder
{
    public function run(): void
    {
        ServiceSlaTemplate::query()->updateOrCreate(
            ['name' => 'Standard Residential'],
            [
                'response_minutes' => 240,
                'resolution_minutes' => 1440,
                'availability_target' => 99.00,
                'support_hours' => ['days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'], 'start' => '08:00', 'end' => '18:00'],
                'escalation_rules' => [['after_minutes' => 240, 'to' => 'supervisor']],
                'priority_matrix' => ['high' => 60, 'normal' => 240, 'low' => 480],
                'is_active' => true,
            ],
        );

        ServiceSlaTemplate::query()->updateOrCreate(
            ['name' => 'Business Priority'],
            [
                'response_minutes' => 60,
                'resolution_minutes' => 480,
                'availability_target' => 99.50,
                'support_hours' => ['days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'], 'start' => '07:00', 'end' => '22:00'],
                'escalation_rules' => [['after_minutes' => 60, 'to' => 'noc']],
                'priority_matrix' => ['critical' => 15, 'high' => 30, 'normal' => 60],
                'is_active' => true,
            ],
        );
    }
}
