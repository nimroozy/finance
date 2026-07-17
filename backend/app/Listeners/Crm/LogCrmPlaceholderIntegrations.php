<?php

namespace App\Listeners\Crm;

use App\Events\BusinessNotificationRequested;
use App\Events\Crm\FollowUpOverdue;
use App\Events\Crm\InstallationRequestedFromCrm;
use App\Events\Crm\LeadConverted;
use App\Events\Crm\PlaceholderRadiusActivationRequested;
use App\Events\Crm\PlaceholderZohoCustomerRequested;
use App\Events\Crm\QuotationAccepted;
use App\Events\Crm\SurveyCompleted;
use Illuminate\Support\Facades\Log;

class LogCrmPlaceholderIntegrations
{
    public function handleRadius(PlaceholderRadiusActivationRequested $event): void
    {
        Log::info('crm.placeholder.radius_activation_requested', [
            'lead_id' => $event->leadId,
            'customer_id' => $event->customerId,
            'branch_id' => $event->branchId,
        ]);

        BusinessNotificationRequested::dispatch('crm_placeholder_radius_activation', [
            'branch_id' => $event->branchId,
            'lead_id' => $event->leadId,
            'customer_id' => $event->customerId,
            'title' => 'Radius activation queued (placeholder)',
        ], ['in_app']);
    }

    public function handleZoho(PlaceholderZohoCustomerRequested $event): void
    {
        Log::info('crm.placeholder.zoho_customer_requested', [
            'lead_id' => $event->leadId,
            'customer_id' => $event->customerId,
            'branch_id' => $event->branchId,
        ]);

        BusinessNotificationRequested::dispatch('crm_placeholder_zoho_customer', [
            'branch_id' => $event->branchId,
            'lead_id' => $event->leadId,
            'customer_id' => $event->customerId,
            'title' => 'Zoho customer create queued (placeholder)',
        ], ['in_app']);
    }

    public function handleConverted(LeadConverted $event): void
    {
        Log::info('crm.lead.converted', [
            'lead_id' => $event->leadId,
            'customer_id' => $event->customerId,
            'installation_id' => $event->installationId,
            'branch_id' => $event->branchId,
        ]);
    }

    public function handleInstallationRequested(InstallationRequestedFromCrm $event): void
    {
        Log::info('crm.installation_requested', [
            'lead_id' => $event->leadId,
            'installation_id' => $event->installationId,
            'branch_id' => $event->branchId,
        ]);
    }

    public function handleSurveyCompleted(SurveyCompleted $event): void
    {
        Log::info('crm.survey.completed', [
            'survey_id' => $event->surveyId,
            'lead_id' => $event->leadId,
            'branch_id' => $event->branchId,
        ]);
    }

    public function handleQuotationAccepted(QuotationAccepted $event): void
    {
        Log::info('crm.quotation.accepted', [
            'quotation_id' => $event->quotationId,
            'lead_id' => $event->leadId,
            'branch_id' => $event->branchId,
        ]);
    }

    public function handleFollowUpOverdue(FollowUpOverdue $event): void
    {
        Log::info('crm.follow_up.overdue', [
            'follow_up_id' => $event->followUpId,
            'branch_id' => $event->branchId,
        ]);
    }
}
