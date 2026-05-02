<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     * This is the SPA shell — it renders ONCE per browser tab lifetime.
     * SIP connects here and never restarts on navigation.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     * When this changes (new deploy), Inertia forces a full reload once
     * so the new JS bundle is picked up — this is the only intentional reload.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data shared with every Inertia page component as props.
     * Access in React via: const { auth, flash, settings } = usePage().props
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [

            // Authenticated user — available in every React page
            'auth' => [
                'user' => $request->user() ? [
                    'id'     => $request->user()->id,
                    'name'   => $request->user()->name,
                    'email'  => $request->user()->email,
                    'role'   => $request->user()->role,
                ] : null,
            ],

            // Flash messages — replaces session('success') / session('error') in Blade
            // React reads these and shows Bootstrap toasts automatically
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],

            // Site settings needed by the layout (logo, name, call provider)
            'settings' => [
                'site_name'             => \App\Models\Setting::get('site_name', 'Admission CRM'),
                'primary_call_provider' => \App\Models\Setting::get('primary_call_provider', 'tcn'),
            ],
        ]);
    }
}
