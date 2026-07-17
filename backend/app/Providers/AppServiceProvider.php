<?php

namespace App\Providers;

use App\Events\AttachmentUploaded;
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
use App\Listeners\EmitBusinessNotificationFromTicketEvents;
use App\Models\Branch;
use App\Models\CollectionRoute;
use App\Models\CollectionVisit;
use App\Models\CustomerAssignment;
use App\Models\CustomerNote;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\PromiseToPay;
use App\Models\Receipt;
use App\Models\UploadedVisitFile;
use App\Models\User;
use App\Policies\BranchPolicy;
use App\Policies\CollectionRoutePolicy;
use App\Policies\CollectionVisitPolicy;
use App\Policies\CustomerAssignmentPolicy;
use App\Policies\CustomerNotePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PaymentReversalPolicy;
use App\Policies\PromiseToPayPolicy;
use App\Policies\ReceiptPolicy;
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

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('login', $request->ip()));
        });

        $this->registerOperationalEventListeners();
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
