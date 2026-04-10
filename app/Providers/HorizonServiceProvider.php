<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Route Horizon's built-in alerts (long wait, failed jobs, etc.).
        $alertEmail = trim((string) env('HORIZON_ALERT_EMAIL', ''));
        if ($alertEmail !== '') {
            Horizon::routeMailNotificationsTo($alertEmail);
        }

        $slackWebhook = trim((string) env('HORIZON_SLACK_WEBHOOK', ''));
        $slackChannel = trim((string) env('HORIZON_SLACK_CHANNEL', ''));
        if ($slackWebhook !== '' && $slackChannel !== '') {
            Horizon::routeSlackNotificationsTo($slackWebhook, $slackChannel);
        }
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
