<?php

namespace App\Providers;

use App\Events\AttachmentUploaded;
use App\Events\Crm\FollowUpOverdue;
use App\Events\Crm\InstallationRequestedFromCrm;
use App\Events\Crm\LeadConverted;
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
use App\Events\Services\ServiceActivated;
use App\Events\Services\ServiceCancelled;
use App\Events\Services\ServiceCreated;
use App\Events\Services\ServiceFinanceHoldPlaced;
use App\Events\Services\ServicePackageChanged;
use App\Events\Services\ServiceReactivated;
use App\Events\Services\ServiceRelocated;
use App\Events\Services\ServiceSuspended;
use App\Listeners\Crm\LogCrmDomainEvents;
use App\Listeners\EmitBusinessNotificationFromTicketEvents;
use App\Listeners\Services\EmitBusinessNotificationFromServiceEvents;
use App\Models\Branch;
use App\Models\CollectionRoute;
use App\Models\CollectionVisit;
use App\Models\Crm\Lead;
use App\Models\Inventory\CustodyRecord;
use App\Models\Inventory\Equipment;
use App\Models\Inventory\EquipmentSale;
use App\Models\Inventory\FixedAsset;
use App\Models\Inventory\Location;
use App\Models\Inventory\MaintenancePlan;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseRequest;
use App\Models\Inventory\Repair;
use App\Models\Inventory\Reservation;
use App\Models\Inventory\Site;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockCount;
use App\Models\Inventory\StockTransaction;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Tower;
use App\Models\Inventory\Transfer;
use App\Models\Services\Service;
use App\Support\PermissionRegistry;
use App\Policies\Inventory\CustodyRecordPolicy;
use App\Policies\Inventory\EquipmentPolicy;
use App\Policies\Inventory\EquipmentSalePolicy;
use App\Policies\Inventory\FixedAssetPolicy;
use App\Policies\Inventory\LocationPolicy;
use App\Policies\Inventory\MaintenancePlanPolicy;
use App\Policies\Inventory\ProductCategoryPolicy;
use App\Policies\Inventory\ProductPolicy;
use App\Policies\Inventory\PurchaseOrderPolicy;
use App\Policies\Inventory\PurchaseRequestPolicy;
use App\Policies\Inventory\RepairPolicy;
use App\Policies\Inventory\ReservationPolicy;
use App\Policies\Inventory\SitePolicy;
use App\Policies\Inventory\StockBalancePolicy;
use App\Policies\Inventory\StockCountPolicy;
use App\Policies\Inventory\StockTransactionPolicy;
use App\Policies\Inventory\SupplierPolicy;
use App\Policies\Inventory\TowerPolicy;
use App\Policies\Inventory\TransferPolicy;
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
use App\Policies\Services\ServicePolicy;
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
        PermissionRegistry::registerGates();

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
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(Tower::class, TowerPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(StockBalance::class, StockBalancePolicy::class);
        Gate::policy(StockTransaction::class, StockTransactionPolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(Transfer::class, TransferPolicy::class);
        Gate::policy(Equipment::class, EquipmentPolicy::class);
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(EquipmentSale::class, EquipmentSalePolicy::class);
        Gate::policy(CustodyRecord::class, CustodyRecordPolicy::class);
        Gate::policy(Repair::class, RepairPolicy::class);
        Gate::policy(MaintenancePlan::class, MaintenancePlanPolicy::class);
        Gate::policy(StockCount::class, StockCountPolicy::class);
        Gate::policy(FixedAsset::class, FixedAssetPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);

        RateLimiter::for('login', function (Request $request) {
            if (app()->environment('acceptance', 'testing')) {
                return \Illuminate\Cache\RateLimiting\Limit::none();
            }

            return Limit::perMinute(5)->by($request->input('login', $request->ip()));
        });

        $this->registerOperationalEventListeners();
        $this->registerCrmEventListeners();
        $this->registerServiceLifecycleEventListeners();
    }

    private function registerServiceLifecycleEventListeners(): void
    {
        $notifyFrom = [
            ServiceCreated::class,
            ServiceActivated::class,
            ServiceSuspended::class,
            ServiceReactivated::class,
            ServiceCancelled::class,
            ServicePackageChanged::class,
            ServiceRelocated::class,
            ServiceFinanceHoldPlaced::class,
        ];

        foreach ($notifyFrom as $event) {
            Event::listen($event, [EmitBusinessNotificationFromServiceEvents::class, 'onDomainEvent']);
        }
    }

    private function registerCrmEventListeners(): void
    {
        Event::listen(LeadConverted::class, [LogCrmDomainEvents::class, 'handleConverted']);
        Event::listen(InstallationRequestedFromCrm::class, [LogCrmDomainEvents::class, 'handleInstallationRequested']);
        Event::listen(SurveyCompleted::class, [LogCrmDomainEvents::class, 'handleSurveyCompleted']);
        Event::listen(QuotationAccepted::class, [LogCrmDomainEvents::class, 'handleQuotationAccepted']);
        Event::listen(FollowUpOverdue::class, [LogCrmDomainEvents::class, 'handleFollowUpOverdue']);
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
