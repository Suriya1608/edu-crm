<?php

namespace App\Jobs;

use App\Mail\TelecallerDailySummary;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Sends each active manager a daily performance summary for their telecallers.
 * Dispatched by the scheduler at 19:00 every day.
 * Respects the "daily_summary_email_enabled" setting.
 */
class SendDailySummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 180;

    public function handle(): void
    {
        if (Setting::get('daily_summary_email_enabled', '0') !== '1') {
            return;
        }

        $today = now()->toDateString();

        $managers = User::where('role', 'manager')
            ->where('status', 1)
            ->whereNotNull('email')
            ->get();

        foreach ($managers as $manager) {
            try {
                $telecallerIds = Lead::where('assigned_by', $manager->id)
                    ->whereNotNull('assigned_to')
                    ->distinct()
                    ->pluck('assigned_to');

                if ($telecallerIds->isEmpty()) {
                    continue;
                }

                $telecallers = User::whereIn('id', $telecallerIds)->get();

                $summaryRows = $telecallers->map(function (User $telecaller) use ($today, $manager) {
                    $myLeadsQuery = Lead::where('assigned_by', $manager->id)
                        ->where('assigned_to', $telecaller->id)
                        ->select('id');

                    $callsMade = CallLog::where('user_id', $telecaller->id)
                        ->whereDate('created_at', $today)
                        ->count();

                    $talkTimeSec = (int) CallLog::where('user_id', $telecaller->id)
                        ->whereDate('created_at', $today)
                        ->sum('duration');

                    $conversions = Lead::where('assigned_by', $manager->id)
                        ->where('assigned_to', $telecaller->id)
                        ->whereDate('updated_at', $today)
                        ->where('status', 'converted')
                        ->count();

                    $followupsCompleted = Schema::hasColumn('followups', 'completed_at')
                        ? Followup::whereIn('lead_id', $myLeadsQuery)
                            ->whereDate('completed_at', $today)
                            ->count()
                        : 0;

                    $followupsMissed = Followup::whereIn('lead_id', $myLeadsQuery)
                        ->whereDate('next_followup', $today)
                        ->when(Schema::hasColumn('followups', 'completed_at'), fn($q) => $q->whereNull('completed_at'))
                        ->count();

                    return [
                        'name'              => $telecaller->name,
                        'calls_made'        => $callsMade,
                        'talk_time_seconds' => $talkTimeSec,
                        'conversions'       => $conversions,
                        'followups_done'    => $followupsCompleted,
                        'followups_missed'  => $followupsMissed,
                    ];
                })->toArray();

                Mail::to($manager->email)
                    ->send(new TelecallerDailySummary($manager->name, $today, $summaryRows));

                Log::channel('single')->info("[DailySummary] Sent to manager: {$manager->email}");
            } catch (\Throwable $e) {
                Log::channel('single')->error("[DailySummary] Failed for manager {$manager->id}: " . $e->getMessage());
            }
        }
    }
}
