<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\User;
use App\Policies\BranchPolicy;
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

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('login', $request->ip()));
        });
    }
}
