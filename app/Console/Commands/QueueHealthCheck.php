<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class QueueHealthCheck extends Command
{
    protected $signature = 'queue:health-check';
    protected $description = 'Checks Horizon status, queue backlogs, and failed jobs; sends alerts when thresholds are exceeded.';

    public function handle(): int
    {
        $this->checkHorizonStatus();
        $this->checkQueueBacklogs();
        $this->checkFailedJobs();

        return self::SUCCESS;
    }

    private function checkHorizonStatus(): void
    {
        try {
            Artisan::call('horizon:status');
            $status = strtolower((string) Artisan::output());

            if (!str_contains($status, 'running')) {
                $this->sendAlert(
                    'horizon_not_running',
                    'Horizon appears to be stopped.',
                    ['horizon_status_output' => trim((string) Artisan::output())]
                );
            }
        } catch (\Throwable $e) {
            $this->sendAlert(
                'horizon_status_error',
                'Unable to check Horizon status.',
                ['error' => $e->getMessage()]
            );
        }
    }

    private function checkQueueBacklogs(): void
    {
        $queues = (array) config('queue_monitor.queues', []);
        $defaultMax = (int) config('queue_monitor.default_max_pending', 300);
        $thresholds = (array) config('queue_monitor.max_pending', []);

        foreach ($queues as $queue) {
            if ($queue === '') {
                continue;
            }

            try {
                $pending = (int) Redis::connection(config('horizon.use', 'default'))
                    ->llen("queues:{$queue}");
            } catch (\Throwable $e) {
                $this->sendAlert(
                    'queue_depth_error_' . $queue,
                    "Failed to read queue depth for [{$queue}].",
                    ['queue' => $queue, 'error' => $e->getMessage()]
                );
                continue;
            }

            $maxPending = (int) ($thresholds[$queue] ?? $defaultMax);
            if ($pending > $maxPending) {
                $this->sendAlert(
                    'queue_backlog_' . $queue,
                    "Queue backlog too high for [{$queue}] ({$pending} pending > {$maxPending} threshold).",
                    ['queue' => $queue, 'pending' => $pending, 'threshold' => $maxPending]
                );
            }
        }
    }

    private function checkFailedJobs(): void
    {
        if (!Schema::hasTable('failed_jobs')) {
            return;
        }

        $threshold = (int) config('queue_monitor.max_failed_jobs', 20);
        $count = (int) DB::table('failed_jobs')->count();

        if ($count > $threshold) {
            $this->sendAlert(
                'failed_jobs_threshold',
                "Failed jobs exceeded threshold ({$count} > {$threshold}).",
                ['failed_jobs_count' => $count, 'threshold' => $threshold]
            );
        }
    }

    private function sendAlert(string $key, string $message, array $context = []): void
    {
        $cooldown = max(60, (int) config('queue_monitor.alert_cooldown_seconds', 300));
        $cacheKey = "queue-health-alert:{$key}";

        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->addSeconds($cooldown));

        $payload = array_merge([
            'message' => $message,
            'env' => app()->environment(),
            'app' => config('app.name'),
            'at' => now()->toDateTimeString(),
        ], $context);

        Log::critical('[QueueHealthCheck] ' . $message, $payload);

        $email = trim((string) config('queue_monitor.alert_email', ''));
        if ($email !== '') {
            try {
                Mail::raw($this->formatAlertText($payload), function ($mail) use ($email): void {
                    $mail->to($email)
                        ->subject('[CRM] Queue Health Alert');
                });
            } catch (\Throwable $e) {
                Log::error('[QueueHealthCheck] Failed to send queue alert email', [
                    'error' => $e->getMessage(),
                    'to' => $email,
                ]);
            }
        }

        $slackWebhook = trim((string) config('queue_monitor.slack_webhook', ''));
        if ($slackWebhook !== '') {
            try {
                Http::timeout(5)->post($slackWebhook, [
                    'text' => $this->formatAlertText($payload),
                    'channel' => config('queue_monitor.slack_channel', '#crm-ops'),
                ]);
            } catch (\Throwable $e) {
                Log::error('[QueueHealthCheck] Failed to send queue alert to Slack', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function formatAlertText(array $payload): string
    {
        $lines = [
            'CRM Queue Health Alert',
            'App: ' . ($payload['app'] ?? 'crm'),
            'Env: ' . ($payload['env'] ?? 'unknown'),
            'At: ' . ($payload['at'] ?? now()->toDateTimeString()),
            'Message: ' . ($payload['message'] ?? 'n/a'),
        ];

        foreach ($payload as $key => $value) {
            if (in_array($key, ['app', 'env', 'at', 'message'], true)) {
                continue;
            }
            $lines[] = ucfirst((string) $key) . ': ' . (is_scalar($value) ? (string) $value : json_encode($value));
        }

        return implode("\n", $lines);
    }
}
