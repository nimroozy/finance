<?php

namespace Database\Seeders;

use App\Models\WhatsApp\WhatsAppNotificationRule;
use Illuminate\Database\Seeder;

class WhatsAppNotificationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['event_type' => 'payment_confirmed', 'template_name' => 'payment_received'],
            ['event_type' => 'payment_posted', 'template_name' => 'payment_posted_successfully'],
            ['event_type' => 'permanent_ownership_assigned', 'template_name' => 'collector_permanent_assigned'],
            ['event_type' => 'temporary_assignment_created', 'template_name' => 'collector_temporary_assigned'],
            ['event_type' => 'handover_submitted', 'template_name' => 'handover_submitted'],
            ['event_type' => 'handover_approved', 'template_name' => 'handover_approved'],
            ['event_type' => 'handover_rejected', 'template_name' => 'handover_rejected'],
            ['event_type' => 'promise_due_reminder', 'template_name' => 'promise_due_reminder'],
            ['event_type' => 'zoho_sync_failed', 'template_name' => 'zoho_sync_failed'],
        ];

        foreach ($rules as $rule) {
            WhatsAppNotificationRule::updateOrCreate(
                ['branch_id' => null, 'event_type' => $rule['event_type']],
                [
                    'in_app_enabled' => true,
                    'whatsapp_enabled' => false,
                    'template_name' => $rule['template_name'],
                    'language_code' => 'fa',
                    'language' => 'fa',
                    'is_active' => true,
                ],
            );
        }
    }
}
