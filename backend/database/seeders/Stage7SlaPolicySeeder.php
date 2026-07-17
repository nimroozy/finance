<?php

namespace Database\Seeders;

use App\Models\Tickets\SlaPolicy;
use App\Models\Tickets\Ticket;
use Illuminate\Database\Seeder;

class Stage7SlaPolicySeeder extends Seeder
{
    public function run(): void
    {
        $businessHours = [
            'timezone' => 'Asia/Kabul',
            'days' => [
                'saturday' => ['09:00', '17:00'],
                'sunday' => ['09:00', '17:00'],
                'monday' => ['09:00', '17:00'],
                'tuesday' => ['09:00', '17:00'],
                'wednesday' => ['09:00', '17:00'],
                'thursday' => ['09:00', '17:00'],
            ],
        ];

        $pauseStatuses = [
            Ticket::STATUS_WAITING_CUSTOMER,
            Ticket::STATUS_WAITING_EQUIPMENT,
            Ticket::STATUS_SCHEDULED,
            Ticket::STATUS_RESOLVED,
            Ticket::STATUS_CLOSED,
            Ticket::STATUS_CANCELLED,
        ];

        $policies = [
            [
                'name' => 'Default Critical',
                'priority' => Ticket::PRIORITY_CRITICAL,
                'response_minutes' => 15,
                'resolution_minutes' => 240,
                'escalation_intervals' => [15, 30, 60],
            ],
            [
                'name' => 'Default High',
                'priority' => Ticket::PRIORITY_HIGH,
                'response_minutes' => 30,
                'resolution_minutes' => 480,
                'escalation_intervals' => [30, 60, 120],
            ],
            [
                'name' => 'Default Normal',
                'priority' => Ticket::PRIORITY_NORMAL,
                'response_minutes' => 60,
                'resolution_minutes' => 1440,
                'escalation_intervals' => [60, 240, 480],
            ],
            [
                'name' => 'Default Medium',
                'priority' => Ticket::PRIORITY_MEDIUM,
                'response_minutes' => 60,
                'resolution_minutes' => 1440,
                'escalation_intervals' => [60, 240, 480],
            ],
            [
                'name' => 'Default Low',
                'priority' => Ticket::PRIORITY_LOW,
                'response_minutes' => 240,
                'resolution_minutes' => 2880,
                'escalation_intervals' => [240, 480, 1440],
            ],
        ];

        foreach ($policies as $policy) {
            SlaPolicy::query()->updateOrCreate(
                [
                    'name' => $policy['name'],
                    'branch_id' => null,
                    'ticket_type_code' => null,
                    'priority' => $policy['priority'],
                    'customer_tier' => null,
                ],
                [
                    'business_hours' => $businessHours,
                    'response_minutes' => $policy['response_minutes'],
                    'resolution_minutes' => $policy['resolution_minutes'],
                    'escalation_intervals' => $policy['escalation_intervals'],
                    'pause_statuses' => $pauseStatuses,
                    'is_active' => true,
                ]
            );
        }
    }
}
