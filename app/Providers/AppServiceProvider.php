<?php

namespace App\Providers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Observers\LeadActivityObserver;
use App\Policies\LeadPolicy;
use App\Policies\SettingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * Policies are registered here so they are available before boot().
     */
    public function register(): void
    {
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // Grant admins all abilities automatically — checked before any policy
        Gate::before(function (User $user, string $ability) {
            if ($user->role === 'admin') {
                return true;
            }
        });
    }

    public function boot(): void
    {
        LeadActivity::observe(LeadActivityObserver::class);

        Paginator::useBootstrapFive();

        RedirectIfAuthenticated::redirectUsing(function () {
            $user = Auth::user();
            if (! $user) {
                return '/';
            }
            return match ($user->role) {
                'admin'      => route('admin.dashboard'),
                'manager'    => route('manager.dashboard'),
                'telecaller' => route('telecaller.dashboard'),
                default      => '/',
            };
        });
        Schema::defaultStringLength(191);
    }
}
