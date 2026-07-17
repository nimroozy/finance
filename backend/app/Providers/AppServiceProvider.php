<?php

namespace App\Providers;

use App\Events\AttachmentUploaded;
use App\Events\Crm\FollowUpOverdue;
use App\Events\Crm\InstallationRequestedFromCrm;
use App\Events\Crm\LeadConverted;
use App\Events\Crm\PlaceholderRadiusActivationRequested;
use App\Events\Crm\PlaceholderZohoCustomerRequested;
use App\Events\Crm\QuotationAccepted;
use App\Events\Crm\SurveyCompleted;
use App\Events\InstallationStatusChanged;
use App\Events\TaskAccepted;
use App\Events\TaskBlocked;
use App\Events\TaskCompleted;
use App\Events\TaskCreated;
use App\Events\TaskOffered;
use App\Events\TaskReassigned;
use App\Events\TaskRejected;
use App\Events\TaskStarted;
use App\Events\TaskVerified;
use App\Events\TicketAssigned;
use App\Events\TicketClosed;
use App\Events\TicketEscalated;
use App\Events\TicketOpened;
use App\Events\TicketReopened;
use App\Events\TicketResolved;
use App\Events\TicketStatusChanged;
use App\Events\WorkLogAdded;
use App\Listeners\Crm\LogCrmPlaceholderIntegrations;
use App\Listeners\EmitBusinessNotificationFromTicketEvents;
use App\Models\Branch;
use App\Models\CollectionRoute;
use App\Models\CollectionVisit;
use App\Models\Crm\Lead;
use App\Models\CustomerAssignment;
use App\Models\CustomerNote;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\PromiseToPay;
use App\Models\Receipt;
use App\Models\Tickets\Installation;
use App\Models\Tickets\OperationalAttachment;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\UploadedVisitFile;
use App\Models\User;
use App\Policies\BranchPolicy;
use App\Policies\CollectionRoutePolicy;
use App\Policies\CollectionVisitPolicy;
use App\Policies\Crm\LeadPolicy;
use App\Policies\CustomerAssignmentPolicy;
use App\Policies\CustomerNotePolicy;
use App\Policies\InstallationPolicy;
use App\Policies\OperationalAttachmentPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PaymentReversalPolicy;
use App\Policies\PromiseToPayPolicy;
use App\Policies\ReceiptPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TicketPolicy;
use App\Policies\UploadedVisitFilePolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(CustomerAssignment::class, CustomerAssignmentPolicy::class);
        Gate::policy(CollectionVisit::class, CollectionVisitPolicy::class);
        Gate::policy(CollectionRoute::class, CollectionRoutePolicy::class);
        Gate::policy(PromiseToPay::class, PromiseToPayPolicy::class);
        Gate::policy(CustomerNote::class, CustomerNotePolicy::class);
        Gate::policy(UploadedVisitFile::class, UploadedVisitFilePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PaymentReversal::class, PaymentReversalPolicy::class);
        Gate::policy(Receipt::class, ReceiptPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Installation::class, InstallationPolicy::class);
        Gate::policy(OperationalAttachment::class, OperationalAttachmentPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('login', $request->ip()));
        });

        $this->registerOperationalEventListeners();
        $this->registerCrmEventListeners();
    }

    private function registerCrmEventListeners(): void
    {
        Event::listen(PlaceholderRadiusActivationRequested::class, [LogCrmPlaceholderIntegrations::class, 'handleRadius']);
        Event::listen(PlaceholderZohoCustomerRequested::class, [LogCrmPlaceholderIntegrations::class, 'handleZoho']);
        Event::listen(LeadConverted::class, [LogCrmPlaceholderIntegrations::class, 'handleConverted']);
        Event::listen(InstallationRequestedFromCrm::class, [LogCrmPlaceholderIntegrations::class, 'handleInstallationRequested']);
        Event::listen(SurveyCompleted::class, [LogCrmPlaceholderIntegrations::class, 'handleSurveyCompleted']);
        Event::listen(QuotationAccepted::class, [LogCrmPlaceholderIntegrations::class, 'handleQuotationAccepted']);
        Event::listen(FollowUpOverdue::class, [LogCrmPlaceholderIntegrations::class, 'handleFollowUpOverdue']);
    }

    private function registerOperationalEventListeners(): void
    {
        // HandleInboundMessageForTicketing is auto-discovered via typed handle().

        $notifyFrom = [
            TicketOpened::class,
            TicketAssigned::class,
            TicketStatusChanged::class,
            TicketEscalated::class,
            TicketResolved::class,
            TicketClosed::class,
            TicketReopened::class,
            TaskCreated::class,
            TaskOffered::class,
            TaskAccepted::class,
            TaskRejected::class,
            TaskReassigned::class,
            TaskStarted::class,
            TaskCompleted::class,
            TaskVerified::class,
            TaskBlocked::class,
            InstallationStatusChanged::class,
            WorkLogAdded::class,
            AttachmentUploaded::class,
        ];

        foreach ($notifyFrom as $event) {
            Event::listen($event, [EmitBusinessNotificationFromTicketEvents::class, 'onDomainEvent']);
        }
    }
}
