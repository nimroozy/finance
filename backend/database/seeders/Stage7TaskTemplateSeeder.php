<?php

namespace Database\Seeders;

use App\Models\Tickets\TaskTemplate;
use Illuminate\Database\Seeder;

class Stage7TaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        TaskTemplate::query()->updateOrCreate(
            ['code' => 'installation_standard'],
            [
                'name' => 'Standard Installation Workflow',
                'workflow_type' => TaskTemplate::WORKFLOW_INSTALLATION,
                'is_active' => true,
                'steps' => [
                    [
                        'key' => 'site_survey',
                        'title' => 'Site Survey',
                        'department_code' => 'technical',
                        'requires_evidence' => ['survey_photo', 'gps'],
                        'order' => 1,
                    ],
                    [
                        'key' => 'finance_approval',
                        'title' => 'Finance Approval',
                        'department_code' => 'finance',
                        'requires_approval' => true,
                        'order' => 2,
                        'depends_on' => ['site_survey'],
                    ],
                    [
                        'key' => 'equipment_prep',
                        'title' => 'Equipment Preparation',
                        'department_code' => 'inventory',
                        'order' => 3,
                        'depends_on' => ['finance_approval'],
                    ],
                    [
                        'key' => 'field_install',
                        'title' => 'Field Installation',
                        'department_code' => 'installation',
                        'requires_evidence' => ['install_photo', 'signal_reading'],
                        'order' => 4,
                        'depends_on' => ['equipment_prep'],
                    ],
                    [
                        'key' => 'noc_activation',
                        'title' => 'NOC / Radius Activation',
                        'department_code' => 'noc',
                        'order' => 5,
                        'depends_on' => ['field_install'],
                    ],
                    [
                        'key' => 'customer_acceptance',
                        'title' => 'Customer Acceptance',
                        'department_code' => 'customer_support',
                        'order' => 6,
                        'depends_on' => ['noc_activation'],
                    ],
                ],
            ]
        );

        TaskTemplate::query()->updateOrCreate(
            ['code' => 'ticket_field_visit'],
            [
                'name' => 'Ticket Field Visit',
                'workflow_type' => TaskTemplate::WORKFLOW_TICKET,
                'is_active' => true,
                'steps' => [
                    [
                        'key' => 'dispatch',
                        'title' => 'Dispatch Technician',
                        'department_code' => 'technical',
                        'order' => 1,
                    ],
                    [
                        'key' => 'onsite_work',
                        'title' => 'On-site Work',
                        'department_code' => 'technical',
                        'requires_evidence' => ['work_photo', 'gps'],
                        'order' => 2,
                        'depends_on' => ['dispatch'],
                    ],
                    [
                        'key' => 'verify_close',
                        'title' => 'Verify and Close',
                        'department_code' => 'noc',
                        'order' => 3,
                        'depends_on' => ['onsite_work'],
                    ],
                ],
            ]
        );
    }
}
