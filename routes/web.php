<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LeadManagementController as AdminLeadManagementController;
use App\Http\Controllers\Admin\ReportsController as AdminReportsController;
use App\Http\Controllers\Admin\AutomationController as AdminAutomationController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Api\LeadApiController;
use App\Http\Controllers\Manager\LeadController as ManagerLeadController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Manager\CallLogController as ManagerCallLogController;
use App\Http\Controllers\Manager\FollowupManagementController;
use App\Http\Controllers\Manager\ReportsController as ManagerReportsController;
use App\Http\Controllers\Telecaller\LeadController as TeleLeadController;
use App\Http\Controllers\Telecaller\FollowupController as TeleFollowupController;
use App\Http\Controllers\Telecaller\PerformanceController as TelePerformanceController;
use App\Http\Controllers\Telecaller\CallManagementController as TeleCallManagementController;
use App\Http\Controllers\LeadImportController;
use App\Http\Controllers\LeadExportController;
use App\Http\Controllers\Manager\LeadExportController as ManagerLeadExportController;
use App\Http\Controllers\FollowupController;
use App\Http\Controllers\TwilioController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use App\Http\Controllers\Manager\ManagerTelecallerController;
use App\Http\Controllers\MetaWhatsAppController;
use App\Http\Controllers\Manager\WhatsAppChatController;
use App\Http\Controllers\Api\LeadCaptureController;
use Illuminate\Http\Request;
use App\Http\Controllers\ExotelController;
use App\Http\Controllers\Admin\CampaignPerformanceController as AdminCampaignPerformanceController;
use App\Http\Controllers\Admin\Marketing\SocialMediaController;
use App\Http\Controllers\Admin\Settings\FacebookLeadsSettingController;
use App\Http\Controllers\Admin\Settings\LeadPortalsSettingController;
use App\Http\Controllers\Admin\PageSettingsController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\TelecallerStatusController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Manager\CampaignController as ManagerCampaignController;
use App\Http\Controllers\Telecaller\CampaignController as TeleCampaignController;
use App\Http\Controllers\Admin\DocumentController;
Route::get('/', function () {
    return view('auth.login');
});

// Public pages (no auth required)
Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('pages.privacy');
Route::get('/terms-of-service', [PageController::class, 'termsOfService'])->name('pages.terms');

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin'])->prefix('admin')
    ->name('admin.')   //✅ Important
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Users Module
        |--------------------------------------------------------------------------
        */

        Route::get('/users', [UserController::class, 'index'])
            ->name('users');
        Route::get('/users/admins', [UserController::class, 'admins'])
            ->name('users.admins');
        Route::get('/users/managers', [UserController::class, 'managers'])
            ->name('users.managers');
        Route::get('/users/telecallers', [UserController::class, 'telecallers'])
            ->name('users.telecallers');

        Route::get('/users/create', [UserController::class, 'create'])
            ->name('users.create');

        Route::post('/users/store', [UserController::class, 'store'])
            ->name('users.store');

        Route::get('/users/edit/{id}', [UserController::class, 'edit'])
            ->name('users.edit');

        Route::post('/users/update/{id}', [UserController::class, 'update'])
            ->name('users.update');

        Route::post('/users/toggle-status', [UserController::class, 'toggleStatus'])
            ->name('users.toggle');
        Route::post('/users/force-logout', [UserController::class, 'forceLogout'])
            ->name('users.force-logout');
        Route::post('/users/reset-password', [UserController::class, 'resetPassword'])
            ->name('users.reset-password');
        Route::post('/users/unlock', [UserController::class, 'unlockAccount'])
            ->name('users.unlock');
        Route::get('/users/presence-snapshot', [UserController::class, 'presenceSnapshot'])
            ->name('users.presence-snapshot');

        Route::prefix('leads')->name('leads.')->group(function () {
            Route::get('/all', [AdminLeadManagementController::class, 'all'])->name('all');
            Route::get('/unassigned', [AdminLeadManagementController::class, 'unassigned'])->name('unassigned');
            Route::get('/assigned', [AdminLeadManagementController::class, 'assigned'])->name('assigned');
            Route::get('/converted', [AdminLeadManagementController::class, 'converted'])->name('converted');
            Route::get('/lost', [AdminLeadManagementController::class, 'lost'])->name('lost');
            Route::get('/duplicates', [AdminLeadManagementController::class, 'duplicates'])->name('duplicates');

            Route::post('/{id}/assign-manager', [AdminLeadManagementController::class, 'assignManager'])->name('assign-manager');
            Route::post('/{id}/reassign-telecaller', [AdminLeadManagementController::class, 'reassignTelecaller'])->name('reassign-telecaller');
            Route::post('/bulk-assign', [AdminLeadManagementController::class, 'bulkAssign'])->name('bulk-assign');
            Route::post('/{id}/merge/{targetId}', [AdminLeadManagementController::class, 'merge'])->name('merge');

            Route::get('/import/form', [AdminLeadManagementController::class, 'importForm'])->name('import.form');
            Route::post('/import/preview', [AdminLeadManagementController::class, 'importPreview'])->name('import.preview');
            Route::post('/import/store', [AdminLeadManagementController::class, 'importStore'])->name('import.store');
            Route::get('/export', [AdminLeadManagementController::class, 'export'])->name('export');
            Route::get('/{id}', [AdminLeadManagementController::class, 'show'])->name('show');
        });

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/telecaller-performance', [AdminReportsController::class, 'telecallerPerformance'])->name('telecaller-performance');
            Route::get('/manager-performance', [AdminReportsController::class, 'managerPerformance'])->name('manager-performance');
            Route::get('/conversion', [AdminReportsController::class, 'conversion'])->name('conversion');
            Route::get('/lead-source', [AdminReportsController::class, 'sourcePerformance'])->name('lead-source');
            Route::get('/period', [AdminReportsController::class, 'period'])->name('period');
            Route::get('/call-efficiency', [AdminReportsController::class, 'callEfficiency'])->name('call-efficiency');
            Route::get('/response-time', [AdminReportsController::class, 'responseTime'])->name('response-time');
            Route::get('/export/{report}/{format}', [AdminReportsController::class, 'export'])->name('export');
        });

        Route::prefix('automation')->name('automation.')->group(function () {
            Route::get('/lead-assignment', [AdminAutomationController::class, 'leadAssignment'])->name('lead-assignment');
            Route::post('/lead-assignment', [AdminAutomationController::class, 'updateLeadAssignment'])->name('lead-assignment.update');
            Route::get('/followup-reminders', [AdminAutomationController::class, 'followupReminder'])->name('followup-reminders');
            Route::post('/followup-reminders', [AdminAutomationController::class, 'updateFollowupReminder'])->name('followup-reminders.update');
            Route::get('/escalation', [AdminAutomationController::class, 'escalation'])->name('escalation');
            Route::post('/escalation', [AdminAutomationController::class, 'updateEscalation'])->name('escalation.update');
        });


        /*
        |--------------------------------------------------------------------------
        | Marketing Module
        |--------------------------------------------------------------------------
        */

        Route::prefix('marketing')
            ->name('marketing.')
            ->group(function () {

                Route::get('/social-media', [SocialMediaController::class, 'index'])
                    ->name('social.media');

                Route::get('/facebook/connect', [SocialMediaController::class, 'redirectToFacebook'])
                    ->name('facebook.connect');

                Route::get('/facebook/callback', [SocialMediaController::class, 'handleFacebookCallback'])
                    ->name('facebook.callback');
            });

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/general', [SettingsController::class, 'edit'])->name('general');
            Route::post('/general', [SettingsController::class, 'update'])->name('general.update');

            Route::get('/smtp', [SystemSettingsController::class, 'smtp'])->name('smtp');
            Route::post('/smtp', [SystemSettingsController::class, 'updateSmtp'])->name('smtp.update');
            Route::post('/smtp/test', [SystemSettingsController::class, 'testSmtp'])->name('smtp.test');

            Route::get('/sms', [SystemSettingsController::class, 'sms'])->name('sms');
            Route::post('/sms', [SystemSettingsController::class, 'updateSms'])->name('sms.update');

            Route::get('/whatsapp', [SystemSettingsController::class, 'whatsapp'])->name('whatsapp');
            Route::post('/whatsapp', [SystemSettingsController::class, 'updateWhatsapp'])->name('whatsapp.update');

            Route::get('/twilio', [SystemSettingsController::class, 'twilio'])->name('twilio');
            Route::post('/twilio', [SystemSettingsController::class, 'updateTwilio'])->name('twilio.update');

            Route::get('/business-hours', [SystemSettingsController::class, 'businessHours'])->name('business-hours');
            Route::post('/business-hours', [SystemSettingsController::class, 'updateBusinessHours'])->name('business-hours.update');

            Route::get('/working-days', [SystemSettingsController::class, 'workingDays'])->name('working-days');
            Route::post('/working-days', [SystemSettingsController::class, 'updateWorkingDays'])->name('working-days.update');

            Route::get('/timezone', [SystemSettingsController::class, 'timezone'])->name('timezone');
            Route::post('/timezone', [SystemSettingsController::class, 'updateTimezone'])->name('timezone.update');

            Route::get('/default-lead-status', [SystemSettingsController::class, 'defaultLeadStatus'])->name('default-lead-status');
            Route::post('/default-lead-status', [SystemSettingsController::class, 'updateDefaultLeadStatus'])->name('default-lead-status.update');

            Route::get('/notifications', [SystemSettingsController::class, 'notifications'])->name('notifications');
            Route::post('/notifications', [SystemSettingsController::class, 'updateNotifications'])->name('notifications.update');

            Route::get('/pages', [PageSettingsController::class, 'edit'])->name('pages');
            Route::post('/pages', [PageSettingsController::class, 'update'])->name('pages.update');

            Route::get('/facebook-leads', [FacebookLeadsSettingController::class, 'show'])->name('facebook-leads');
            Route::post('/facebook-leads', [FacebookLeadsSettingController::class, 'update'])->name('facebook-leads.update');

            Route::get('/lead-portals', [LeadPortalsSettingController::class, 'show'])->name('lead-portals');
            Route::post('/lead-portals', [LeadPortalsSettingController::class, 'update'])->name('lead-portals.update');

            Route::get('/security', [SettingsController::class, 'security'])->name('security');
            Route::post('/security', [SettingsController::class, 'updateSecurity'])->name('security.update');
        });

        Route::get('/settings', fn() => redirect()->route('admin.settings.general'))->name('settings');

        Route::get('/campaigns/performance', [AdminCampaignPerformanceController::class, 'index'])
            ->name('campaigns.performance');
        Route::get('/campaigns/contacts', [AdminCampaignPerformanceController::class, 'contacts'])
            ->name('campaigns.contacts');

        // Documents
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    });

/*
|--------------------------------------------------------------------------
| MANAGER
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('manager')->group(function () {
    Route::get('/dashboard', [ManagerDashboardController::class, 'index'])
        ->name('manager.dashboard');
    Route::get('/call-logs', [ManagerCallLogController::class, 'index'])
        ->name('manager.call-logs.index');
    Route::prefix('reports')->name('manager.reports.')->group(function () {
        Route::get('/home', [ManagerReportsController::class, 'home'])->name('home');
        Route::get('/telecaller-performance', [ManagerReportsController::class, 'telecallerPerformance'])->name('telecaller-performance');
        Route::get('/conversion', [ManagerReportsController::class, 'conversion'])->name('conversion');
        Route::get('/source-performance', [ManagerReportsController::class, 'sourcePerformance'])->name('source-performance');
        Route::get('/period', [ManagerReportsController::class, 'period'])->name('period');
        Route::get('/response-time', [ManagerReportsController::class, 'responseTime'])->name('response-time');
        Route::get('/call-efficiency', [ManagerReportsController::class, 'callEfficiency'])->name('call-efficiency');
        Route::get('/export/{report}/{format}', [ManagerReportsController::class, 'export'])->name('export');
    });

    // Leads List
    Route::get('/leads', [ManagerLeadController::class, 'index'])
        ->name('manager.leads');

    Route::get('/leads/create', [ManagerLeadController::class, 'create'])
        ->name('manager.leads.create');

    Route::post('/leads/store', [ManagerLeadController::class, 'store'])
        ->name('manager.leads.store');

    // IMPORTANT: Import & Export MUST BE ABOVE {id}
    Route::get('/leads/import', [LeadImportController::class, 'index'])
        ->name('manager.leads.import');

    Route::post('/leads/import/preview', [LeadImportController::class, 'preview'])
        ->name('manager.leads.import.preview');

    Route::post('/leads/import/store', [LeadImportController::class, 'store'])
        ->name('manager.leads.import.store');

    Route::get('/leads/export', [ManagerLeadExportController::class, 'export'])
        ->name('manager.leads.export');

    Route::get('/leads/duplicates', [ManagerLeadController::class, 'duplicates'])
        ->name('manager.leads.duplicates');

    // Lead Details (KEEP LAST)
    Route::get('/leads/{id}', [ManagerLeadController::class, 'show'])
        ->name('manager.leads.show');

    Route::post('/leads/{id}/assign', [ManagerLeadController::class, 'assign'])
        ->name('manager.assign');

    Route::get('/leads/import/sample', [LeadImportController::class, 'downloadSample'])
        ->name('manager.leads.import.sample');

    Route::post('/leads/{id}/change-status', [ManagerLeadController::class, 'changeStatus'])
        ->name('manager.leads.changeStatus');
    Route::post('/leads/{id}/add-note', [ManagerLeadController::class, 'addNote'])
        ->name('manager.leads.addNote');
    Route::post('/leads/{id}/whatsapp', [MetaWhatsAppController::class, 'sendLeadMessage'])
        ->name('manager.leads.whatsapp.store');
    Route::post('/leads/{id}/whatsapp/media', [MetaWhatsAppController::class, 'sendMedia'])
        ->name('manager.leads.whatsapp.media');
    Route::get('/leads/{id}/whatsapp/messages', [MetaWhatsAppController::class, 'fetchMessages'])
        ->name('manager.leads.whatsapp.fetch');

    // WhatsApp Chat Hub
    Route::get('/whatsapp', [WhatsAppChatController::class, 'index'])
        ->name('manager.whatsapp.hub');
    Route::get('/whatsapp/messages/{id}', [WhatsAppChatController::class, 'messages'])
        ->name('manager.whatsapp.messages');

    Route::post('/leads/{id}/change-status', [ManagerLeadController::class, 'changeStatus'])
        ->name('manager.leads.changeStatus');
    Route::get('/telecallers', [ManagerTelecallerController::class, 'index'])
        ->name('manager.telecallers');
    Route::post('/manager/log-call', [ManagerLeadController::class, 'logCall'])
        ->name('manager.log.call');
    Route::get('/telecaller-status/snapshot', [TelecallerStatusController::class, 'managerSnapshot'])
        ->name('manager.telecaller-status.snapshot');

    Route::prefix('followups')->name('manager.followups.')->group(function () {
        Route::get('/today', [FollowupManagementController::class, 'today'])->name('today');
        Route::get('/overdue', [FollowupManagementController::class, 'overdue'])->name('overdue');
        Route::get('/upcoming', [FollowupManagementController::class, 'upcoming'])->name('upcoming');
        Route::get('/missed-by-telecaller', [FollowupManagementController::class, 'missedByTelecaller'])->name('missed');
    });

    Route::post('/notifications/read-all', [FollowupManagementController::class, 'markAllNotificationsRead'])
        ->name('manager.notifications.read-all');
    Route::get('/notifications/snapshot', [FollowupManagementController::class, 'notificationsSnapshot'])
        ->name('manager.notifications.snapshot');
    Route::get('/whatsapp/inbox-poll', [FollowupManagementController::class, 'whatsappInboxPoll'])
        ->name('manager.whatsapp.inbox-poll');

    Route::post('/status/heartbeat', [TelecallerStatusController::class, 'managerHeartbeat'])
        ->name('manager.status.heartbeat');

    // ── Outbound Campaigns ──────────────────────────────────────────────────
    Route::get('/campaigns', [ManagerCampaignController::class, 'index'])
        ->name('manager.campaigns.index');
    Route::get('/campaigns/create', [ManagerCampaignController::class, 'create'])
        ->name('manager.campaigns.create');
    Route::post('/campaigns', [ManagerCampaignController::class, 'store'])
        ->name('manager.campaigns.store');
    Route::get('/campaigns/{id}', [ManagerCampaignController::class, 'show'])
        ->name('manager.campaigns.show');
    Route::patch('/campaigns/{id}/status', [ManagerCampaignController::class, 'updateStatus'])
        ->name('manager.campaigns.status');
    Route::get('/campaigns/{id}/import', [ManagerCampaignController::class, 'importForm'])
        ->name('manager.campaigns.import');
    Route::post('/campaigns/{id}/import/preview', [ManagerCampaignController::class, 'importPreview'])
        ->name('manager.campaigns.import.preview');
    Route::post('/campaigns/{id}/import/store', [ManagerCampaignController::class, 'importStore'])
        ->name('manager.campaigns.import.store');
    Route::post('/campaigns/{id}/distribute', [ManagerCampaignController::class, 'distribute'])
        ->name('manager.campaigns.distribute');

    // ── Campaign Contact Detail (Manager) ────────────────────────────────────
    Route::get('/campaigns/{campaignId}/contacts/{contactId}', [ManagerCampaignController::class, 'contact'])
        ->name('manager.campaigns.contact');
    Route::patch('/campaigns/{campaignId}/contacts/{contactId}/status', [ManagerCampaignController::class, 'updateContactStatus'])
        ->name('manager.campaigns.contact.status');
    Route::post('/campaigns/{campaignId}/contacts/{contactId}/followup', [ManagerCampaignController::class, 'setContactFollowup'])
        ->name('manager.campaigns.contact.followup');
    Route::post('/campaigns/{campaignId}/contacts/{contactId}/note', [ManagerCampaignController::class, 'addContactNote'])
        ->name('manager.campaigns.contact.note');
    Route::post('/campaigns/{campaignId}/contacts/{contactId}/call', [ManagerCampaignController::class, 'logContactCall'])
        ->name('manager.campaigns.contact.call');
    Route::patch('/campaigns/{campaignId}/contacts/{contactId}/reassign', [ManagerCampaignController::class, 'reassignContact'])
        ->name('manager.campaigns.contact.reassign');
    Route::post('/campaigns/{campaignId}/contacts/{contactId}/whatsapp', [MetaWhatsAppController::class, 'sendCampaignContactMessage'])
        ->name('manager.campaigns.contact.whatsapp.store');
    Route::post('/campaigns/{campaignId}/contacts/{contactId}/whatsapp/media', [MetaWhatsAppController::class, 'sendCampaignContactMedia'])
        ->name('manager.campaigns.contact.whatsapp.media');
    Route::get('/campaigns/{campaignId}/contacts/{contactId}/whatsapp/messages', [MetaWhatsAppController::class, 'fetchCampaignContactMessages'])
        ->name('manager.campaigns.contact.whatsapp.fetch');

    // ── Campaign Performance Dashboard ───────────────────────────────────────
    Route::get('/campaigns-performance', [ManagerCampaignController::class, 'performance'])
        ->name('manager.campaigns.performance');
});

/*
|--------------------------------------------------------------------------
| TELECALLER
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('telecaller')->name('telecaller.')->group(function () {
    Route::get('/dashboard', [TeleLeadController::class, 'dashboard'])
        ->name('dashboard');

    Route::get('/leads', [TeleLeadController::class, 'index'])
        ->name('leads');

    Route::get('/leads/{id}', [TeleLeadController::class, 'show'])
        ->name('leads.show');

    Route::post('/followup/store', [TeleLeadController::class, 'storeFollowup'])
        ->name('followup.store');

    Route::post('/call/{id}', [TeleLeadController::class, 'callLead'])
        ->name('call');

    Route::post('/leads/status/{id}', [TeleLeadController::class, 'changeStatus'])
        ->name('leads.changeStatus');

    Route::post('/leads/note/{id}', [TeleLeadController::class, 'addNote'])
        ->name('leads.addNote');
    Route::post('/leads/{id}/whatsapp', [MetaWhatsAppController::class, 'sendLeadMessage'])
        ->name('leads.whatsapp.store');
    Route::post('/leads/{id}/whatsapp/media', [MetaWhatsAppController::class, 'sendMedia'])
        ->name('leads.whatsapp.media');
    Route::get('/leads/{id}/whatsapp/messages', [MetaWhatsAppController::class, 'fetchMessages'])
        ->name('leads.whatsapp.fetch');

    Route::post('/status/heartbeat', [TelecallerStatusController::class, 'heartbeat'])
        ->name('status.heartbeat');
    Route::post('/status/offline', [TelecallerStatusController::class, 'offline'])
        ->name('status.offline');
    Route::post('/status/availability', [TelecallerStatusController::class, 'setAvailability'])
        ->name('status.availability');
    Route::get('/panel/snapshot', [TeleLeadController::class, 'panelSnapshot'])
        ->name('panel.snapshot');
    Route::get('/notifications/snapshot', [TelecallerStatusController::class, 'notificationsSnapshot'])
        ->name('notifications.snapshot');
    Route::post('/notifications/read-all', [TelecallerStatusController::class, 'markNotificationsRead'])
        ->name('notifications.read-all');
    Route::get('/whatsapp/inbox-poll', [TelecallerStatusController::class, 'whatsappInboxPoll'])
        ->name('whatsapp.inbox-poll');

    Route::prefix('followups')->name('followups.')->group(function () {
        Route::get('/today', [TeleFollowupController::class, 'today'])->name('today');
        Route::get('/overdue', [TeleFollowupController::class, 'overdue'])->name('overdue');
        Route::get('/upcoming', [TeleFollowupController::class, 'upcoming'])->name('upcoming');
        Route::get('/completed', [TeleFollowupController::class, 'completed'])->name('completed');
        Route::post('/{id}/reschedule', [TeleFollowupController::class, 'reschedule'])->name('reschedule');
        Route::post('/{id}/complete', [TeleFollowupController::class, 'markCompleted'])->name('mark-complete');
    });

    Route::prefix('performance')->name('performance.')->group(function () {
        Route::get('/daily', [TelePerformanceController::class, 'daily'])->name('daily');
        Route::get('/weekly', [TelePerformanceController::class, 'weekly'])->name('weekly');
        Route::get('/monthly', [TelePerformanceController::class, 'monthly'])->name('monthly');
    });

    Route::prefix('calls')->name('calls.')->group(function () {
        Route::get('/outbound', [TeleCallManagementController::class, 'outbound'])->name('outbound');
        Route::get('/inbound', [TeleCallManagementController::class, 'inbound'])->name('inbound');
        Route::get('/missed', [TeleCallManagementController::class, 'missed'])->name('missed');
        Route::get('/history', [TeleCallManagementController::class, 'history'])->name('history');
    });

    // ── Outbound Campaigns ──────────────────────────────────────────────────
    Route::prefix('campaigns')->name('campaigns.')->group(function () {
        Route::get('/', [TeleCampaignController::class, 'index'])->name('index');
        Route::get('/{campaignId}', [TeleCampaignController::class, 'show'])->name('show');
        Route::get('/{campaignId}/contacts/{contactId}', [TeleCampaignController::class, 'contact'])->name('contact');
        Route::post('/{campaignId}/contacts/{contactId}/note', [TeleCampaignController::class, 'addNote'])->name('contact.note');
        Route::patch('/{campaignId}/contacts/{contactId}/status', [TeleCampaignController::class, 'updateStatus'])->name('contact.status');
        Route::post('/{campaignId}/contacts/{contactId}/followup', [TeleCampaignController::class, 'setFollowup'])->name('contact.followup');
        Route::post('/{campaignId}/contacts/{contactId}/call', [TeleCampaignController::class, 'logCall'])->name('contact.call');
        Route::post('/{campaignId}/contacts/{contactId}/whatsapp', [MetaWhatsAppController::class, 'sendCampaignContactMessage'])->name('contact.whatsapp.store');
        Route::post('/{campaignId}/contacts/{contactId}/whatsapp/media', [MetaWhatsAppController::class, 'sendCampaignContactMedia'])->name('contact.whatsapp.media');
        Route::get('/{campaignId}/contacts/{contactId}/whatsapp/messages', [MetaWhatsAppController::class, 'fetchCampaignContactMessages'])->name('contact.whatsapp.fetch');
    });

});


/*
|--------------------------------------------------------------------------
| COMMON
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Documents — shared access (all roles can list + download)
    Route::get('/documents/list', [DocumentController::class, 'list'])->name('documents.list');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/view', [DocumentController::class, 'view'])->name('documents.view');

    Route::post('/followups/store', [FollowupController::class, 'store'])
        ->name('followups.store');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

Route::post('/lead-capture', [LeadCaptureController::class, 'store']);

/*
|--------------------------------------------------------------------------
| API / WEBHOOKS
|--------------------------------------------------------------------------
*/


Route::post('/crm-store-lead', [LeadCaptureController::class, 'store'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Meta WhatsApp Cloud API webhooks (GET = verification, POST = events)
Route::match(['get', 'post'], '/webhooks/meta/whatsapp', [MetaWhatsAppController::class, 'webhook'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('meta.whatsapp.webhook');

// Meta Facebook Lead Ads webhook (GET = verification, POST = lead events)
Route::match(['get', 'post'], '/webhooks/meta/facebook', [SocialMediaController::class, 'webhook'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('meta.facebook.webhook');

Route::post('/exotel/connect', function (Request $request) {
    return response('<Response>
        <Dial>' . $request->lead . '</Dial>
    </Response>', 200)->header('Content-Type', 'text/xml');
})->name('exotel.connect.callback');

Route::post('/manager/exotel-call', [ManagerLeadController::class, 'makeCall'])
    ->name('manager.exotel.call')
    ->middleware('auth');

Route::post('/exotel/token', [ExotelController::class, 'generateToken'])
    ->name('exotel.token');


// Route::post(
//     '/webhook/exotel',
//     [\App\Http\Controllers\WebhookController::class, 'exotel']
// );

/*
|--------------------------------------------------------------------------
| TWILIO
|--------------------------------------------------------------------------
*/
// Route::get('/twilio/token', [TwilioController::class, 'generateToken'])
//     ->middleware('auth')
//     ->name('twilio.token');

// Route::post('/twilio/voice', [TwilioController::class, 'voice'])
//     ->withoutMiddleware([VerifyCsrfToken::class])
//     ->name('twilio.voice');

// Route::post('/twilio/callback', [TwilioController::class, 'callback'])
//     ->withoutMiddleware([VerifyCsrfToken::class])
//     ->name('twilio.callback');
// Route::post('/twilio/voice', [TwilioController::class, 'voice']);
Route::match(['GET', 'POST'], '/twilio/voice', [TwilioController::class, 'voice']);
Route::post('/twilio/voice', [TwilioController::class,'voice']);
Route::post('/twilio/status', [TwilioController::class,'status'])->name('twilio.status');
Route::post('/twilio/recording', [TwilioController::class,'recording'])->name('twilio.recording');
Route::post('/call/start', [CallController::class,'startCall']);
Route::post('/call/end', [CallController::class,'endCall']);
Route::post('/call/outcome', [CallController::class,'recordOutcome'])->name('call.outcome');
Route::post('/call/update-sid', [CallController::class,'updateCallSid']);
Route::post('/webhook/incoming-call', [CallController::class, 'incomingCall'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhook.incoming-call');
Route::post('/webhook/call-status', [CallController::class, 'callStatusWebhook'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhook.call-status');
Route::middleware(['auth'])->group(function () {

    Route::get('/twilio/token', [TwilioController::class, 'generateToken']);
});
require __DIR__ . '/auth.php';
