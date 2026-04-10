<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadCaptureController;
use App\Http\Controllers\Api\LeadApiController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\EmailWebhookController;
use App\Http\Middleware\VerifyInboundLeadToken;

/*
|--------------------------------------------------------------------------
| Public Routes  (no token required)
|--------------------------------------------------------------------------
*/

// Authentication
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');
});

// Lead capture from external forms / landing pages
Route::post('/lead-capture', [LeadCaptureController::class, 'store'])
    ->middleware([VerifyInboundLeadToken::class, 'throttle:30,1'])
    ->name('api.lead.capture');

// Email open-tracking pixel
Route::get('/campaigns/track/open/{campaignId}/{recipientId}', [EmailTrackingController::class, 'open'])
    ->whereNumber(['campaignId', 'recipientId'])
    ->name('api.email.open');

// Bounce webhook — SES / Mailgun / SendGrid
// Subscribe SNS topic to: POST {APP_URL}/api/campaigns/bounce?provider=ses
Route::post('/campaigns/bounce', [EmailWebhookController::class, 'bounce'])
    ->name('api.campaigns.bounce');

/*
|--------------------------------------------------------------------------
| Authenticated Routes  (Sanctum bearer token required)
| Header: Authorization: Bearer {token}
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth helpers
    Route::prefix('auth')->name('api.auth.')->group(function () {
        Route::get('/me',         [AuthController::class, 'me'])->name('me');
        Route::post('/logout',    [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
    });

    // Leads  (LeadApiController scopes results by the caller's role)
    Route::middleware('throttle:60,1')->prefix('leads')->name('api.leads.')->group(function () {
        Route::get('/',     [LeadApiController::class, 'index'])->name('index');
        Route::get('/{id}', [LeadApiController::class, 'show'])->name('show');
    });
});
