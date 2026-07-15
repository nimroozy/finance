<?php

namespace App\Providers;

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
    }
}
