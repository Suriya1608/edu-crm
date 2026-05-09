<?php

namespace App\Providers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Setting;
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
        if (!Schema::hasTable('settings')) {
            view()->share('globalSettings', collect());
            return;
        }

        $settings = Setting::pluck('value', 'key');
        $safeKeys = [
            'site_name',
            'site_url',
            'site_logo',
            'site_favicon',
            'telephony_provider',
            'system_timezone',
        ];
        view()->share('globalSettings', $settings->only($safeKeys));

        $timezone = (string) ($settings['system_timezone'] ?? config('app.timezone', 'UTC'));
        if (in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        config([
            'mail.default' => (string) ($settings['smtp_mailer'] ?? env('MAIL_MAILER', 'smtp')),
            'mail.mailers.smtp.host' => (string) ($settings['smtp_host'] ?? env('MAIL_HOST', '127.0.0.1')),
            'mail.mailers.smtp.port' => (int) ($settings['smtp_port'] ?? env('MAIL_PORT', 2525)),
            'mail.mailers.smtp.encryption' => (string) ($settings['smtp_encryption'] ?? env('MAIL_ENCRYPTION')),
            'mail.mailers.smtp.username' => (string) Setting::getSecure('smtp_username', env('MAIL_USERNAME')),
            'mail.mailers.smtp.password' => (string) Setting::getSecure('smtp_password', env('MAIL_PASSWORD')),
            'mail.from.address' => (string) ($settings['smtp_from_address'] ?? env('MAIL_FROM_ADDRESS', 'hello@example.com')),
            'mail.from.name' => (string) ($settings['smtp_from_name'] ?? env('MAIL_FROM_NAME', 'CRM')),
        ]);

        // Dynamic broadcast driver — read from DB settings, override compiled config
        $broadcastDriver = (string) ($settings['broadcast_driver'] ?? 'null');
        config(['broadcasting.default' => $broadcastDriver]);

        if ($broadcastDriver === 'pusher') {
            config([
                'broadcasting.connections.pusher.key'             => Setting::getSecure('pusher_app_key',    env('PUSHER_APP_KEY', '')),
                'broadcasting.connections.pusher.secret'          => Setting::getSecure('pusher_app_secret', env('PUSHER_APP_SECRET', '')),
                'broadcasting.connections.pusher.app_id'          => Setting::getSecure('pusher_app_id',     env('PUSHER_APP_ID', '')),
                'broadcasting.connections.pusher.options.cluster' => (string) ($settings['pusher_app_cluster'] ?? env('PUSHER_APP_CLUSTER', 'mt1')),
            ]);
        } elseif ($broadcastDriver === 'reverb') {
            config([
                'broadcasting.connections.reverb.key'            => Setting::getSecure('reverb_app_key',    env('REVERB_APP_KEY', '')),
                'broadcasting.connections.reverb.secret'         => Setting::getSecure('reverb_app_secret', env('REVERB_APP_SECRET', '')),
                'broadcasting.connections.reverb.app_id'         => Setting::getSecure('reverb_app_id',     env('REVERB_APP_ID', '')),
                'broadcasting.connections.reverb.options.host'   => (string) ($settings['reverb_host']   ?? env('REVERB_HOST', '0.0.0.0')),
                'broadcasting.connections.reverb.options.port'   => (int)    ($settings['reverb_port']   ?? env('REVERB_PORT', 8080)),
                'broadcasting.connections.reverb.options.scheme' => (string) ($settings['reverb_scheme'] ?? env('REVERB_SCHEME', 'http')),
            ]);
        }
    }
}
