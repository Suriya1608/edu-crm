<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| CRM Automation Schedule
|--------------------------------------------------------------------------
| Run `php artisan schedule:run` every minute via Windows Task Scheduler:
|   Action: php c:\wamp64\www\edu-crm\artisan schedule:run
|
| Queue worker must also be running:
|   php artisan queue:work --queue=default --tries=2 --timeout=300
*/

// Dispatch escalation + follow-up reminder jobs to the queue every minute
Schedule::command('crm:run-automation')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Send daily telecaller summary emails to all managers at 7 PM
Schedule::command('crm:daily-summary')
    ->dailyAt('19:00')
    ->withoutOverlapping();

// Dispatch jobs for email campaigns whose scheduled time has arrived
Schedule::command('email:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Horizon: take a metrics snapshot every 5 minutes (powers the throughput graphs)
Schedule::command('horizon:snapshot')->everyFiveMinutes();
