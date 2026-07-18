<?php

namespace Database\Seeders;

use App\Models\Tickets\EscalationRule;
use App\Models\Tickets\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class Stage7EscalationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'name' => 'Critical response overdue → Branch Manager',
                'priority' => Ticket::PRIORITY_CRITICAL,
                'trigger_type' => 'time',
                'trigger_after_minutes' => 15,
                'from_status' => Ticket::STATUS_NEW,
                'escalate_to_role' => User::ROLE_BRANCH_MANAGER,
                'escalate_to_department_code' => 'management',
                'notify_whatsapp' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'High unresolved → NOC',
                'priority' => Ticket::PRIORITY_HIGH,
                'trigger_type' => 'time',
                'trigger_after_minutes' => 120,
                'from_status' => null,
                'escalate_to_role' => null,
                'escalate_to_department_code' => 'noc',
                'notify_whatsapp' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Medium SLA breach → Management',
                'priority' => Ticket::PRIORITY_MEDIUM,
                'trigger_type' => 'sla_breach',
                'trigger_after_minutes' => null,
                'from_status' => null,
                'escalate_to_role' => User::ROLE_BRANCH_MANAGER,
                'escalate_to_department_code' => 'management',
                'notify_whatsapp' => false,
                'sort_order' => 30,
            ],
            [
                'name' => 'Finance tickets overdue → Central Finance',
                'priority' => null,
                'department_code' => 'finance',
                'trigger_type' => 'time',
                'trigger_after_minutes' => 480,
                'from_status' => Ticket::STATUS_IN_PROGRESS,
                'escalate_to_role' => User::ROLE_CENTRAL_FINANCE,
                'escalate_to_department_code' => 'finance',
                'notify_whatsapp' => false,
                'sort_order' => 40,
            ],
            [
                'name' => 'Escalated tickets → Branch Manager',
                'priority' => null,
                'trigger_type' => 'status',
                'trigger_after_minutes' => null,
                'from_status' => Ticket::STATUS_ESCALATED,
                'escalate_to_role' => User::ROLE_BRANCH_MANAGER,
                'escalate_to_department_code' => 'management',
                'notify_whatsapp' => true,
                'sort_order' => 50,
            ],
        ];

        foreach ($rules as $rule) {
            EscalationRule::query()->updateOrCreate(
                ['name' => $rule['name'], 'branch_id' => null],
                array_merge([
                    'ticket_type_code' => null,
                    'department_code' => $rule['department_code'] ?? null,
                    'escalate_to_user_id' => null,
                    'is_active' => true,
                    'meta' => null,
                ], $rule)
            );
        }
    }
}
