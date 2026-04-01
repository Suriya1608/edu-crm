<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Uncomment to enable email/Slack notifications for long waits or failures:
        // Horizon::routeMailNotificationsTo('admin@yourdomain.com');
        // Horizon::routeSlackNotificationsTo(env('HORIZON_SLACK_WEBHOOK'), '#crm-ops');
    }

    /**
     * Only admin-role users can access the Horizon dashboard.
     * In local environment Horizon is accessible to everyone (no gate check).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return $user && $user->role === 'admin';
        });
    }
}
