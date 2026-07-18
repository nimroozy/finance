<?php

namespace App\Listeners\Crm;

use App\Events\Crm\FollowUpOverdue;
use App\Events\Crm\InstallationRequestedFromCrm;
use App\Events\Crm\LeadConverted;
use App\Events\Crm\QuotationAccepted;
use App\Events\Crm\SurveyCompleted;
use Illuminate\Support\Facades\Log;

/**
 * CRM domain event logging for conversion, installation request, survey,
 * quotation, and follow-up overdue. Does not enqueue Radius activation or
 * Zoho customer-create side effects (link existing Zoho-mirrored customers only).
 */
class LogCrmDomainEvents
{
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
