<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME', env('APP_NAME', 'CRM') . ' Horizon'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    // Uses the default Redis connection (DB 0)
    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:'
    ),

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    | If a queue waits longer than these thresholds (seconds), a
    | LongWaitDetected event fires — useful for alerting.
    */
    'waits' => [
        'redis:default'       => 60,
        'redis:emails'        => 120,
        'redis:notifications' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times (in minutes)
    |--------------------------------------------------------------------------
    */
    'trim' => [
        'recent'         => 60,      // Keep recent jobs 1 hour
        'pending'        => 60,
        'completed'      => 60,
        'recent_failed'  => 10080,   // Keep failed jobs 1 week
        'failed'         => 10080,
        'monitored'      => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    | Jobs that run every minute (scheduler) are noisy — silence them from
    | the completed jobs list. They still appear if they fail.
    */
    'silenced' => [
        App\Jobs\DispatchEscalations::class,
        App\Jobs\DispatchFollowupReminders::class,
    ],

    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Three supervisors:
    |
    |  supervisor-default  — General jobs: escalations, reminders, daily summary
    |  supervisor-emails   — Email campaign sends (slow, higher timeout)
    |  supervisor-high     — High-priority one-off tasks
    |
    | In production, balance=auto lets Horizon scale workers up/down
    | based on queue depth. In local, keep 1-2 processes.
    */
    'defaults' => [
        'supervisor-default' => [
            'connection'          => 'redis',
            'queue'               => ['default', 'notifications'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses'        => 5,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 3,
            'timeout'             => 90,
            'nice'                => 0,
        ],
        'supervisor-emails' => [
            'connection'          => 'redis',
            'queue'               => ['emails'],
            'balance'             => 'simple',
            'autoScalingStrategy' => 'time',
            'maxProcesses'        => 3,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 256,
            'tries'               => 2,
            'timeout'             => 300,  // 5 min for large campaign sends
            'nice'                => 5,    // Lower CPU priority
        ],
        'supervisor-high' => [
            'connection'          => 'redis',
            'queue'               => ['high'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses'        => 5,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 3,
            'timeout'             => 60,
            'nice'                => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'maxProcesses'      => 10,
                'balanceMaxShift'   => 2,
                'balanceCooldown'   => 3,
            ],
            'supervisor-emails' => [
                'maxProcesses'      => 5,
                'balanceMaxShift'   => 1,
                'balanceCooldown'   => 5,
            ],
            'supervisor-high' => [
                'maxProcesses'      => 8,
                'balanceMaxShift'   => 2,
                'balanceCooldown'   => 2,
            ],
        ],

        'local' => [
            'supervisor-default' => [
                'maxProcesses' => 2,
            ],
            'supervisor-emails' => [
                'maxProcesses' => 1,
            ],
            'supervisor-high' => [
                'maxProcesses' => 1,
            ],
        ],

        'staging' => [
            'supervisor-default' => [
                'maxProcesses'    => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-emails' => [
                'maxProcesses' => 2,
            ],
            'supervisor-high' => [
                'maxProcesses' => 3,
            ],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        '.env',
    ],
];
