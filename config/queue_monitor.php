<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Queue Health Monitor
    |--------------------------------------------------------------------------
    | Used by `php artisan queue:health-check`.
    */
    'queues' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('QUEUE_MONITOR_QUEUES', 'default,notifications,emails,high'))
    ))),

    // Fallback threshold for any queue not defined below.
    'default_max_pending' => (int) env('QUEUE_MONITOR_DEFAULT_MAX_PENDING', 300),

    // Per-queue backlog thresholds.
    'max_pending' => [
        'default'       => (int) env('QUEUE_MONITOR_MAX_PENDING_DEFAULT', 300),
        'notifications' => (int) env('QUEUE_MONITOR_MAX_PENDING_NOTIFICATIONS', 500),
        'emails'        => (int) env('QUEUE_MONITOR_MAX_PENDING_EMAILS', 200),
        'high'          => (int) env('QUEUE_MONITOR_MAX_PENDING_HIGH', 100),
    ],

    // Failed jobs table threshold.
    'max_failed_jobs' => (int) env('QUEUE_MONITOR_MAX_FAILED_JOBS', 20),

    // Prevent alert spam.
    'alert_cooldown_seconds' => (int) env('QUEUE_MONITOR_ALERT_COOLDOWN_SECONDS', 300),

    // Optional direct alert channels for health-check command.
    'alert_email' => (string) env('QUEUE_MONITOR_ALERT_EMAIL', ''),
    'slack_webhook' => (string) env('QUEUE_MONITOR_SLACK_WEBHOOK', ''),
    'slack_channel' => (string) env('QUEUE_MONITOR_SLACK_CHANNEL', '#crm-ops'),
];

