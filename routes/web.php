<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\FollowupController;
use App\Http\Controllers\LeadImportController;
use App\Http\Controllers\TelecallerStatusController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ExotelController;
use App\Http\Controllers\TcnController;
use App\Http\Controllers\TwilioController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\MetaWhatsAppController;
use App\Http\Controllers\InstagramController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\EmailWebhookController;
use App\Http\Controllers\Api\LeadCaptureController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\TcnSettingsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LeadManagementController as AdminLeadManagementController;
use App\Http\Controllers\Admin\ReportsController as AdminReportsController;
use App\Http\Controllers\Admin\AutomationController as AdminAutomationController;
use App\Http\Controllers\Admin\PageSettingsController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\EmailCampaignController as AdminEmailCampaignController;
use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Admin\CampaignPerformanceController as AdminCampaignPerformanceController;
use App\Http\Controllers\Admin\Marketing\SocialMediaController;
use App\Http\Controllers\Admin\Settings\FacebookLeadsSettingController;
use App\Http\Controllers\Admin\Settings\LeadPortalsSettingController;
use App\Http\Controllers\Manager\LeadController as ManagerLeadController;
use App\Http\Controllers\Manager\LeadExportController as ManagerLeadExportController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Manager\CallLogController as ManagerCallLogController;
use App\Http\Controllers\Manager\FollowupManagementController;
use App\Http\Controllers\Manager\ReportsController as ManagerReportsController;
use App\Http\Controllers\Manager\ManagerTelecallerController;
use App\Http\Controllers\Manager\WhatsAppChatController;
use App\Http\Controllers\Manager\CampaignController as ManagerCampaignController;
use App\Http\Controllers\Manager\EmailCampaignController as ManagerEmailCampaignController;
use App\Http\Controllers\Telecaller\LeadController as TeleLeadController;
use App\Http\Controllers\Telecaller\FollowupController as TeleFollowupController;
use App\Http\Controllers\Telecaller\PerformanceController as TelePerformanceController;
use App\Http\Controllers\Telecaller\CallManagementController as TeleCallManagementController;
use App\Http\Controllers\Telecaller\WhatsAppChatController as TeleWhatsAppChatController;
use App\Http\Controllers\Telecaller\CampaignController as TeleCampaignController;

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
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        /*
        |------------------------------------------------------------------
        | Users Module
        |------------------------------------------------------------------
        */
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::get('/users/admins', [UserController::class, 'admins'])->name('users.admins');
        Route::get('/users/managers', [UserController::class, 'managers'])->name('users.managers');
        Route::get('/users/telecallers', [UserController::class, 'telecallers'])->name('users.telecallers');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users/store', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/edit/{id}', [UserController::class, 'edit'])->name('users.edit');
        Route::post('/users/update/{id}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle');
        Route::post('/users/force-logout', [UserController::class, 'forceLogout'])->name('users.force-logout');
        Route::post('/users/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('/users/unlock', [UserController::class, 'unlockAccount'])->name('users.unlock');
        Route::get('/users/presence-snapshot', [UserController::class, 'presenceSnapshot'])->name('users.presence-snapshot');

        /*
        |------------------------------------------------------------------
        | Leads Module
        |------------------------------------------------------------------
        */
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

        /*
        |------------------------------------------------------------------
        | Reports Module
        |------------------------------------------------------------------
        */
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

        /*
        |------------------------------------------------------------------
        | Automation Module
        |------------------------------------------------------------------
        */
        Route::prefix('automation')->name('automation.')->group(function () {
            Route::get('/lead-assignment', [AdminAutomationController::class, 'leadAssignment'])->name('lead-assignment');
            Route::post('/lead-assignment', [AdminAutomationController::class, 'updateLeadAssignment'])->name('lead-assignment.update');
            Route::get('/followup-reminders', [AdminAutomationController::class, 'followupReminder'])->name('followup-reminders');
            Route::post('/followup-reminders', [AdminAutomationController::class, 'updateFollowupReminder'])->name('followup-reminders.update');
            Route::get('/escalation', [AdminAutomationController::class, 'escalation'])->name('escalation');
            Route::post('/escalation', [AdminAutomationController::class, 'updateEscalation'])->name('escalation.update');
        });

        /*
        |------------------------------------------------------------------
        | Marketing Module
        |------------------------------------------------------------------
        */
        Route::prefix('marketing')->name('marketing.')->group(function () {
            Route::get('/social-media', [SocialMediaController::class, 'index'])->name('social.media');
            Route::get('/facebook/connect', [SocialMediaController::class, 'redirectToFacebook'])->name('facebook.connect');
            Route::get('/facebook/callback', [SocialMediaController::class, 'handleFacebookCallback'])->name('facebook.callback');
        });

        /*
        |------------------------------------------------------------------
        | Settings Module
        |------------------------------------------------------------------
        */
        Route::get('/settings', fn() => redirect()->route('admin.settings.general'))->name('settings');

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
            Route::get('/call', [SystemSettingsController::class, 'callSettings'])->name('call');
            Route::post('/call', [SystemSettingsController::class, 'updateCallSettings'])->name('call.update');
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
            Route::get('/instagram', [SystemSettingsController::class, 'instagram'])->name('instagram');
            Route::post('/instagram', [SystemSettingsController::class, 'updateInstagram'])->name('instagram.update');
            Route::get('/voip', [SystemSettingsController::class, 'voipSettings'])->name('voip');
            Route::post('/voip', [SystemSettingsController::class, 'updateVoipSettings'])->name('voip.update');

            // TCN global settings
            Route::get('/tcn',  [TcnSettingsController::class, 'index'])->name('tcn');
            Route::post('/tcn', [TcnSettingsController::class, 'update'])->name('tcn.update');
        });

        /*
        |------------------------------------------------------------------
        | Campaign Performance
        |------------------------------------------------------------------
        */
        Route::get('/campaigns/performance', [AdminCampaignPerformanceController::class, 'index'])->name('campaigns.performance');
        Route::get('/campaigns/contacts', [AdminCampaignPerformanceController::class, 'contacts'])->name('campaigns.contacts');

        /*
        |------------------------------------------------------------------
        | Email Templates
        |------------------------------------------------------------------
        */
        Route::prefix('email-templates')->name('email-templates.')->group(function () {
            Route::get('/', [EmailTemplateController::class, 'index'])->name('index');
            Route::get('/create', [EmailTemplateController::class, 'create'])->name('create');
            Route::post('/', [EmailTemplateController::class, 'store'])->name('store');
            Route::post('/upload-image', [EmailTemplateController::class, 'uploadImage'])->name('upload-image');
            Route::post('/send-test', [EmailTemplateController::class, 'sendTest'])->name('send-test');
            Route::get('/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->name('edit');
            Route::put('/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('update');
            Route::patch('/{emailTemplate}/toggle-status', [EmailTemplateController::class, 'toggleStatus'])->name('toggle-status');
            Route::delete('/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->name('destroy');
        });

        /*
        |------------------------------------------------------------------
        | Email Campaigns
        |------------------------------------------------------------------
        */
        Route::prefix('email-campaigns')->name('email-campaigns.')->group(function () {
            Route::get('/', [AdminEmailCampaignController::class, 'index'])->name('index');
            Route::get('/create', [AdminEmailCampaignController::class, 'create'])->name('create');
            Route::post('/', [AdminEmailCampaignController::class, 'store'])->name('store');
            Route::get('/contacts', [AdminEmailCampaignController::class, 'emailList'])->name('email-list');
            Route::get('/{emailCampaign}', [AdminEmailCampaignController::class, 'show'])->name('show');
            Route::delete('/{emailCampaign}', [AdminEmailCampaignController::class, 'destroy'])->name('destroy');
        });

        /*
        |------------------------------------------------------------------
        | Courses
        |------------------------------------------------------------------
        */
        Route::prefix('courses')->name('courses.')->group(function () {
            Route::get('/', [AdminCourseController::class, 'index'])->name('index');
            Route::get('/create', [AdminCourseController::class, 'create'])->name('create');
            Route::post('/', [AdminCourseController::class, 'store'])->name('store');
            Route::get('/{course}/edit', [AdminCourseController::class, 'edit'])->name('edit');
            Route::put('/{course}', [AdminCourseController::class, 'update'])->name('update');
            Route::delete('/{course}', [AdminCourseController::class, 'destroy'])->name('destroy');
            Route::patch('/{course}/toggle-status', [AdminCourseController::class, 'toggleStatus'])->name('toggle-status');
        });

        /*
        |------------------------------------------------------------------
        | Documents
        |------------------------------------------------------------------
        */
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    });

/*
|--------------------------------------------------------------------------
| MANAGER
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('manager')
    ->name('manager.')
    ->group(function () {

        Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/call-logs', [ManagerCallLogController::class, 'index'])->name('call-logs.index');

        /*
        |------------------------------------------------------------------
        | Leads Module
        |------------------------------------------------------------------
        */
        Route::get('/leads', [ManagerLeadController::class, 'index'])->name('leads');
        Route::get('/leads/create', [ManagerLeadController::class, 'create'])->name('leads.create');
        Route::post('/leads/store', [ManagerLeadController::class, 'store'])->name('leads.store');

        // IMPORTANT: Import & Export & specific paths MUST be above /{id}
        Route::get('/leads/pipeline', [ManagerLeadController::class, 'pipeline'])->name('leads.pipeline');
        Route::get('/leads/pipeline/more', [ManagerLeadController::class, 'pipelineMore'])->name('leads.pipeline.more');
        Route::post('/leads/pipeline-status', [ManagerLeadController::class, 'updatePipelineStatus'])->name('leads.pipeline.status');
        Route::get('/leads/import', [LeadImportController::class, 'index'])->name('leads.import');
        Route::post('/leads/import/preview', [LeadImportController::class, 'preview'])->name('leads.import.preview');
        Route::post('/leads/import/store', [LeadImportController::class, 'store'])->name('leads.import.store');
        Route::get('/leads/import/sample', [LeadImportController::class, 'downloadSample'])->name('leads.import.sample');
        Route::get('/leads/export', [ManagerLeadExportController::class, 'export'])->name('leads.export');
        Route::get('/leads/duplicates', [ManagerLeadController::class, 'duplicates'])->name('leads.duplicates');

        // Lead Details (KEEP LAST in leads group)
        Route::get('/leads/{id}', [ManagerLeadController::class, 'show'])->name('leads.show');
        Route::post('/leads/{id}/assign', [ManagerLeadController::class, 'assign'])->name('assign');
        Route::post('/leads/{id}/change-status', [ManagerLeadController::class, 'changeStatus'])->name('leads.changeStatus');
        Route::post('/leads/{id}/add-note', [ManagerLeadController::class, 'addNote'])->name('leads.addNote');
        Route::post('/leads/{id}/whatsapp', [MetaWhatsAppController::class, 'sendLeadMessage'])->name('leads.whatsapp.store');
        Route::post('/leads/{id}/whatsapp/template', [MetaWhatsAppController::class, 'sendLeadTemplate'])->name('leads.whatsapp.template');
        Route::post('/leads/{id}/whatsapp/media', [MetaWhatsAppController::class, 'sendMedia'])->name('leads.whatsapp.media');
        Route::get('/leads/{id}/whatsapp/messages', [MetaWhatsAppController::class, 'fetchMessages'])->name('leads.whatsapp.fetch');

        /*
        |------------------------------------------------------------------
        | Reports Module
        |------------------------------------------------------------------
        */
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/home', [ManagerReportsController::class, 'home'])->name('home');
            Route::get('/telecaller-performance', [ManagerReportsController::class, 'telecallerPerformance'])->name('telecaller-performance');
            Route::get('/conversion', [ManagerReportsController::class, 'conversion'])->name('conversion');
            Route::get('/source-performance', [ManagerReportsController::class, 'sourcePerformance'])->name('source-performance');
            Route::get('/period', [ManagerReportsController::class, 'period'])->name('period');
            Route::get('/response-time', [ManagerReportsController::class, 'responseTime'])->name('response-time');
            Route::get('/call-efficiency', [ManagerReportsController::class, 'callEfficiency'])->name('call-efficiency');
            Route::get('/export/{report}/{format}', [ManagerReportsController::class, 'export'])->name('export');
        });

        /*
        |------------------------------------------------------------------
        | Follow-ups Module
        |------------------------------------------------------------------
        */
        Route::prefix('followups')->name('followups.')->group(function () {
            Route::get('/today', [FollowupManagementController::class, 'today'])->name('today');
            Route::get('/overdue', [FollowupManagementController::class, 'overdue'])->name('overdue');
            Route::get('/upcoming', [FollowupManagementController::class, 'upcoming'])->name('upcoming');
            Route::get('/missed-by-telecaller', [FollowupManagementController::class, 'missedByTelecaller'])->name('missed');
            Route::get('/calendar-data', [FollowupManagementController::class, 'calendarData'])->name('calendar-data');
        });

        /*
        |------------------------------------------------------------------
        | Telecaller Management
        |------------------------------------------------------------------
        */
        Route::get('/telecallers', [ManagerTelecallerController::class, 'index'])->name('telecallers');
        Route::get('/telecaller-status/snapshot', [TelecallerStatusController::class, 'managerSnapshot'])->name('telecaller-status.snapshot');
        Route::post('/log-call', [ManagerLeadController::class, 'logCall'])->name('log.call');

        /*
        |------------------------------------------------------------------
        | Status & Notifications
        |------------------------------------------------------------------
        */
        Route::post('/status/heartbeat', [TelecallerStatusController::class, 'managerHeartbeat'])->name('status.heartbeat');
        Route::post('/notifications/read-all', [FollowupManagementController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
        Route::get('/notifications/snapshot', [FollowupManagementController::class, 'notificationsSnapshot'])->name('notifications.snapshot');

        /*
        |------------------------------------------------------------------
        | WhatsApp Chat Hub
        |------------------------------------------------------------------
        */
        Route::get('/whatsapp', [WhatsAppChatController::class, 'index'])->name('whatsapp.hub');
        Route::get('/whatsapp/messages/{id}', [WhatsAppChatController::class, 'messages'])->name('whatsapp.messages');
        Route::get('/whatsapp/inbox-poll', [FollowupManagementController::class, 'whatsappInboxPoll'])->name('whatsapp.inbox-poll');

        /*
        |------------------------------------------------------------------
        | Outbound Campaigns
        |------------------------------------------------------------------
        */
        Route::get('/campaigns', [ManagerCampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campaigns/create', [ManagerCampaignController::class, 'create'])->name('campaigns.create');
        Route::post('/campaigns', [ManagerCampaignController::class, 'store'])->name('campaigns.store');
        Route::get('/campaigns-performance', [ManagerCampaignController::class, 'performance'])->name('campaigns.performance');
        Route::get('/campaigns/{id}', [ManagerCampaignController::class, 'show'])->name('campaigns.show');
        Route::patch('/campaigns/{id}/status', [ManagerCampaignController::class, 'updateStatus'])->name('campaigns.status');
        Route::get('/campaigns/{id}/import', [ManagerCampaignController::class, 'importForm'])->name('campaigns.import');
        Route::post('/campaigns/{id}/import/preview', [ManagerCampaignController::class, 'importPreview'])->name('campaigns.import.preview');
        Route::post('/campaigns/{id}/import/store', [ManagerCampaignController::class, 'importStore'])->name('campaigns.import.store');
        Route::post('/campaigns/{id}/distribute', [ManagerCampaignController::class, 'distribute'])->name('campaigns.distribute');

        // Campaign Contact Detail
        Route::get('/campaigns/{campaignId}/contacts/{contactId}', [ManagerCampaignController::class, 'contact'])->name('campaigns.contact');
        Route::patch('/campaigns/{campaignId}/contacts/{contactId}/status', [ManagerCampaignController::class, 'updateContactStatus'])->name('campaigns.contact.status');
        Route::post('/campaigns/{campaignId}/contacts/{contactId}/followup', [ManagerCampaignController::class, 'setContactFollowup'])->name('campaigns.contact.followup');
        Route::post('/campaigns/{campaignId}/contacts/{contactId}/note', [ManagerCampaignController::class, 'addContactNote'])->name('campaigns.contact.note');
        Route::post('/campaigns/{campaignId}/contacts/{contactId}/call', [ManagerCampaignController::class, 'logContactCall'])->name('campaigns.contact.call');
        Route::patch('/campaigns/{campaignId}/contacts/{contactId}/reassign', [ManagerCampaignController::class, 'reassignContact'])->name('campaigns.contact.reassign');
        Route::post('/campaigns/{campaignId}/contacts/{contactId}/whatsapp', [MetaWhatsAppController::class, 'sendCampaignContactMessage'])->name('campaigns.contact.whatsapp.store');
        Route::post('/campaigns/{campaignId}/contacts/{contactId}/whatsapp/media', [MetaWhatsAppController::class, 'sendCampaignContactMedia'])->name('campaigns.contact.whatsapp.media');
        Route::get('/campaigns/{campaignId}/contacts/{contactId}/whatsapp/messages', [MetaWhatsAppController::class, 'fetchCampaignContactMessages'])->name('campaigns.contact.whatsapp.fetch');

        /*
        |------------------------------------------------------------------
        | Email Campaigns
        |------------------------------------------------------------------
        */
        Route::prefix('email-campaigns')->name('email-campaigns.')->group(function () {
            Route::get('/', [ManagerEmailCampaignController::class, 'index'])->name('index');
            Route::get('/create', [ManagerEmailCampaignController::class, 'create'])->name('create');
            Route::post('/', [ManagerEmailCampaignController::class, 'store'])->name('store');
            Route::get('/contacts', [ManagerEmailCampaignController::class, 'emailList'])->name('email-list');
            Route::get('/{emailCampaign}', [ManagerEmailCampaignController::class, 'show'])->name('show');
            Route::delete('/{emailCampaign}', [ManagerEmailCampaignController::class, 'destroy'])->name('destroy');
        });

        /*
        |------------------------------------------------------------------
        | Instagram Chat
        |------------------------------------------------------------------
        */
        Route::prefix('instagram')->name('instagram.')->group(function () {
            Route::get('/', [InstagramController::class, 'index'])->name('index');
            Route::get('/conversations', [InstagramController::class, 'conversations'])->name('conversations');
            Route::get('/conversations/{id}/messages', [InstagramController::class, 'messages'])->name('messages');
            Route::post('/conversations/{id}/reply', [InstagramController::class, 'reply'])->name('reply');
            Route::post('/conversations/{id}/read', [InstagramController::class, 'markRead'])->name('read');
        });

        /*
        |------------------------------------------------------------------
        | Exotel (Manager)
        |------------------------------------------------------------------
        */
        Route::post('/exotel-call', [ManagerLeadController::class, 'makeCall'])->name('exotel.call');
    });

/*
|--------------------------------------------------------------------------
| TELECALLER
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('telecaller')
    ->name('telecaller.')
    ->group(function () {

        Route::get('/dashboard', [TeleLeadController::class, 'dashboard'])->name('dashboard');

        /*
        |------------------------------------------------------------------
        | Leads Module
        |------------------------------------------------------------------
        */
        Route::get('/leads', [TeleLeadController::class, 'index'])->name('leads');
        Route::get('/leads/pipeline', [TeleLeadController::class, 'pipeline'])->name('leads.pipeline');
        Route::post('/leads/pipeline-status', [TeleLeadController::class, 'updatePipelineStatus'])->name('leads.pipeline.status');
        Route::get('/leads/{id}', [TeleLeadController::class, 'show'])->name('leads.show');
        Route::post('/leads/status/{id}', [TeleLeadController::class, 'changeStatus'])->name('leads.changeStatus');
        Route::post('/leads/note/{id}', [TeleLeadController::class, 'addNote'])->name('leads.addNote');
        Route::post('/leads/{id}/whatsapp', [MetaWhatsAppController::class, 'sendLeadMessage'])->name('leads.whatsapp.store');
        Route::post('/leads/{id}/whatsapp/template', [MetaWhatsAppController::class, 'sendLeadTemplate'])->name('leads.whatsapp.template');
        Route::post('/leads/{id}/whatsapp/media', [MetaWhatsAppController::class, 'sendMedia'])->name('leads.whatsapp.media');
        Route::get('/leads/{id}/whatsapp/messages', [MetaWhatsAppController::class, 'fetchMessages'])->name('leads.whatsapp.fetch');

        Route::post('/followup/store', [TeleLeadController::class, 'storeFollowup'])->name('followup.store');
        Route::post('/call/{id}', [TeleLeadController::class, 'callLead'])->name('call');

        /*
        |------------------------------------------------------------------
        | Follow-ups Module
        |------------------------------------------------------------------
        */
        Route::prefix('followups')->name('followups.')->group(function () {
            Route::get('/today', [TeleFollowupController::class, 'today'])->name('today');
            Route::get('/overdue', [TeleFollowupController::class, 'overdue'])->name('overdue');
            Route::get('/upcoming', [TeleFollowupController::class, 'upcoming'])->name('upcoming');
            Route::get('/completed', [TeleFollowupController::class, 'completed'])->name('completed');
            Route::get('/calendar-data', [TeleFollowupController::class, 'calendarData'])->name('calendar-data');
            Route::post('/{id}/reschedule', [TeleFollowupController::class, 'reschedule'])->name('reschedule');
            Route::post('/{id}/complete', [TeleFollowupController::class, 'markCompleted'])->name('mark-complete');
        });

        /*
        |------------------------------------------------------------------
        | Performance Module
        |------------------------------------------------------------------
        */
        Route::prefix('performance')->name('performance.')->group(function () {
            Route::get('/daily', [TelePerformanceController::class, 'daily'])->name('daily');
            Route::get('/weekly', [TelePerformanceController::class, 'weekly'])->name('weekly');
            Route::get('/monthly', [TelePerformanceController::class, 'monthly'])->name('monthly');
        });

        /*
        |------------------------------------------------------------------
        | Call Management
        |------------------------------------------------------------------
        */
        Route::prefix('calls')->name('calls.')->group(function () {
            Route::get('/outbound', [TeleCallManagementController::class, 'outbound'])->name('outbound');
            Route::get('/inbound', [TeleCallManagementController::class, 'inbound'])->name('inbound');
            Route::get('/missed', [TeleCallManagementController::class, 'missed'])->name('missed');
            Route::get('/history', [TeleCallManagementController::class, 'history'])->name('history');
        });

        /*
        |------------------------------------------------------------------
        | Outbound Campaigns
        |------------------------------------------------------------------
        */
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

        /*
        |------------------------------------------------------------------
        | WhatsApp Chat Hub
        |------------------------------------------------------------------
        */
        Route::get('/whatsapp', [TeleWhatsAppChatController::class, 'index'])->name('whatsapp.hub');
        Route::get('/whatsapp/messages/{id}', [TeleWhatsAppChatController::class, 'messages'])->name('whatsapp.messages');

        /*
        |------------------------------------------------------------------
        | Instagram Chat
        |------------------------------------------------------------------
        */
        Route::prefix('instagram')->name('instagram.')->group(function () {
            Route::get('/', [InstagramController::class, 'index'])->name('index');
            Route::get('/conversations', [InstagramController::class, 'conversations'])->name('conversations');
            Route::get('/conversations/{id}/messages', [InstagramController::class, 'messages'])->name('messages');
            Route::post('/conversations/{id}/reply', [InstagramController::class, 'reply'])->name('reply');
            Route::post('/conversations/{id}/read', [InstagramController::class, 'markRead'])->name('read');
        });

        /*
        |------------------------------------------------------------------
        | Status & Notifications
        |------------------------------------------------------------------
        */
        Route::post('/status/heartbeat', [TelecallerStatusController::class, 'heartbeat'])->name('status.heartbeat');
        Route::post('/status/offline', [TelecallerStatusController::class, 'offline'])->name('status.offline');
        Route::post('/status/availability', [TelecallerStatusController::class, 'setAvailability'])->name('status.availability');
        Route::get('/panel/snapshot', [TeleLeadController::class, 'panelSnapshot'])->name('panel.snapshot');
        Route::get('/notifications/snapshot', [TelecallerStatusController::class, 'notificationsSnapshot'])->name('notifications.snapshot');
        Route::post('/notifications/read-all', [TelecallerStatusController::class, 'markNotificationsRead'])->name('notifications.read-all');
        Route::get('/whatsapp/inbox-poll', [TelecallerStatusController::class, 'whatsappInboxPoll'])->name('whatsapp.inbox-poll');
    });

/*
|--------------------------------------------------------------------------
| COMMON (All Authenticated Roles)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Documents — shared access (all roles can list + download)
    Route::get('/documents/list', [DocumentController::class, 'list'])->name('documents.list');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/view', [DocumentController::class, 'view'])->name('documents.view');

    Route::post('/followups/store', [FollowupController::class, 'store'])->name('followups.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/change-password', [ChangePasswordController::class, 'show'])->name('password.change');
    Route::post('/change-password', [ChangePasswordController::class, 'update'])->name('password.change.update');
});

/*
|--------------------------------------------------------------------------
| WEBHOOKS & PUBLIC ENDPOINTS
|--------------------------------------------------------------------------
*/

// Lead Capture (external landing pages / WordPress)
Route::post('/lead-capture', [LeadCaptureController::class, 'store'])
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::post('/crm-store-lead', [LeadCaptureController::class, 'store'])
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::options('/crm-store-lead', function () {
    return response('', 204, [
        'Access-Control-Allow-Origin'  => '*',
        'Access-Control-Allow-Methods' => 'POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With',
        'Access-Control-Max-Age'       => '86400',
    ]);
})->withoutMiddleware([VerifyCsrfToken::class]);

// Meta WhatsApp Cloud API webhooks (GET = verification, POST = events)
Route::match(['get', 'post'], '/webhooks/meta/whatsapp', [MetaWhatsAppController::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('meta.whatsapp.webhook');

// Meta Instagram DM webhooks (GET = hub verification, POST = message events)
Route::match(['get', 'post'], '/webhooks/meta/instagram', [InstagramController::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('meta.instagram.webhook');

// Meta Facebook Lead Ads webhook (GET = verification, POST = lead events)
Route::match(['get', 'post'], '/webhooks/meta/facebook', [SocialMediaController::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('meta.facebook.webhook');

// Email open / click tracking (public)
Route::get('/email/open/{campaignId}/{recipientId}', [EmailTrackingController::class, 'open'])
    ->whereNumber(['campaignId', 'recipientId'])
    ->name('email.open');

Route::get('/email/track/{token}', [EmailTrackingController::class, 'track'])
    ->name('email.track');

Route::get('/email/click/{token}', [EmailTrackingController::class, 'click'])
    ->name('email.click');

Route::post('/email/webhook/bounce', [EmailWebhookController::class, 'bounce'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('email.webhook.bounce');

/*
|--------------------------------------------------------------------------
| TCN SOFTPHONE
|--------------------------------------------------------------------------
*/

// Per-user TCN config API — returns access_token for logged-in user, never exposes secrets
Route::middleware('auth')->get('/api/tcn/config', [TcnController::class, 'userConfig'])->name('api.tcn.config');

// TCN OAuth — connect redirect and callback (no auth middleware needed)
Route::get('/tcn/auth/connect',  [TcnController::class, 'authRedirect'])->name('tcn.auth.connect')->middleware('auth');
Route::get('/tcn/auth/callback', [TcnController::class, 'authCallback'])->name('tcn.auth.callback');

// TCN per-user OAuth — admin connects individual user accounts.
// The static /tcn/auth/callback URI is reused for the callback so that the
// redirect_uri sent to TCN exactly matches the one registered in their OAuth app.
// user_id is passed via session (set in userConnectRedirect) — NOT via the URL.
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin'])
    ->get('/tcn/connect/{encryptedId}', [TcnController::class, 'userConnectRedirect'])
    ->name('tcn.user.connect');

// TCN Softphone iframe page — embedded in layouts via <iframe src="/softphone">
Route::middleware('auth')->get('/softphone', [TcnController::class, 'softphonePage'])->name('softphone');

// TCN Test Console (admin only)
Route::middleware(['auth'])->group(function () {
    Route::get('/tcn/test',        [TcnController::class, 'testPage'])->name('tcn.test');
    Route::post('/tcn/test/token', [TcnController::class, 'testToken'])->name('tcn.test.token');
});

// TCN authenticated proxy routes
Route::middleware('auth')->prefix('tcn')->name('tcn.')->group(function () {
    // Non-sensitive config for the browser
    Route::get('/config',           [TcnController::class, 'config'])->name('config');

    // Login flow (5 steps)
    Route::post('/token',           [TcnController::class, 'token'])->name('token');
    Route::post('/agent',           [TcnController::class, 'agent'])->name('agent');
    Route::post('/skills',          [TcnController::class, 'skills'])->name('skills');
    Route::post('/session',         [TcnController::class, 'session'])->name('session');
    // Session management
    Route::post('/keepalive',       [TcnController::class, 'keepalive'])->name('keepalive');
    Route::post('/status',          [TcnController::class, 'agentStatus'])->name('status');
    Route::post('/set-status',      [TcnController::class, 'setAgentStatus'])->name('set-status');

    // Outbound call — single manualdial/execute endpoint (preferred)
    Route::post('/dial',            [TcnController::class, 'dial'])->name('dial');

    // Legacy 3-step manualdial (kept for fallback / debugging)
    Route::post('/dial-prepare',    [TcnController::class, 'dialPrepare'])->name('dial-prepare');
    Route::post('/dial-process',    [TcnController::class, 'dialProcess'])->name('dial-process');
    Route::post('/dial-start',      [TcnController::class, 'dialStart'])->name('dial-start');

    // Endpoint probe — POST /tcn/dial-debug to find correct manualdial path
    Route::post('/dial-debug',      [TcnController::class, 'dialDebug'])->name('dial-debug');

    // End call
    Route::post('/disconnect',      [TcnController::class, 'disconnect'])->name('disconnect');

    // Call log management
    Route::post('/call-log',        [TcnController::class, 'createCallLog'])->name('call-log.create');
    Route::patch('/call-log/{id}',  [TcnController::class, 'updateCallLog'])->whereNumber('id')->name('call-log.update');
});

/*
|--------------------------------------------------------------------------
| EXOTEL
|--------------------------------------------------------------------------
*/

// Exotel webhooks (public, no CSRF)
// Route::post('/exotel/incoming', [ExotelController::class, 'incomingConnect'])
//     ->withoutMiddleware([VerifyCsrfToken::class])
//     ->name('exotel.incoming');

Route::post('/exotel/outgoing', [ExotelController::class, 'outgoing'])
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::post('/exotel/webhook', [ExotelController::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('exotel.webhook');

// Exotel authenticated endpoints
Route::middleware('auth')->group(function () {
    Route::post('/exotel/token', [ExotelController::class, 'generateToken'])->name('exotel.token');
    Route::post('/exotel/call', [ExotelController::class, 'call'])->name('exotel.call');
    Route::get('/exotel/status/{callLogId}', [ExotelController::class, 'status'])->whereNumber('callLogId')->name('exotel.status');
    Route::get('/settings/voip', [ExotelController::class, 'voipConfig'])->name('settings.voip');
    Route::get('/exotel/incoming-poll', [ExotelController::class, 'incomingPoll'])->name('exotel.incoming-poll');
    Route::post('/exotel/voip-call', [ExotelController::class, 'voipCall'])->name('exotel.voip-call');
    Route::post('/exotel/browser-incoming', [ExotelController::class, 'registerBrowserIncoming'])->name('exotel.browser-incoming');
});

/*
|--------------------------------------------------------------------------
| TWILIO (Legacy)
|--------------------------------------------------------------------------
*/

// Twilio webhooks (public, no CSRF)
Route::post('/twilio/voice', [TwilioController::class, 'voice'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('twilio.voice');

Route::post('/twilio/status', [TwilioController::class, 'status'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('twilio.status');

Route::post('/twilio/recording', [TwilioController::class, 'recording'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('twilio.recording');

Route::post('/webhook/incoming-call', [CallController::class, 'incomingCall'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhook.incoming-call');

Route::post('/webhook/call-status', [CallController::class, 'callStatusWebhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhook.call-status');

// Twilio authenticated endpoints
Route::middleware('auth')->group(function () {
    Route::get('/twilio/token', [TwilioController::class, 'generateToken']);
    Route::post('/call/start', [CallController::class, 'startCall']);
    Route::post('/call/end', [CallController::class, 'endCall']);
    Route::post('/call/outcome', [CallController::class, 'recordOutcome'])->name('call.outcome');
    Route::post('/call/update-sid', [CallController::class, 'updateCallSid']);
});

require __DIR__ . '/auth.php';
