<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailCampaignJob;
use App\Models\EmailCampaign;
use Illuminate\Console\Command;

class ProcessScheduledEmailCampaigns extends Command
{
    protected $signature   = 'email:process-scheduled';
    protected $description = 'Dispatch jobs for email campaigns whose scheduled_at has passed';

    public function handle(): void
    {
        $campaigns = EmailCampaign::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($campaigns as $campaign) {
            $campaign->update(['status' => 'sending']);
            SendEmailCampaignJob::dispatch($campaign->id);
            $this->line("Dispatched campaign #{$campaign->id}: {$campaign->name}");
        }

        if ($campaigns->isEmpty()) {
            $this->line('No scheduled campaigns due.');
        }
    }
}
