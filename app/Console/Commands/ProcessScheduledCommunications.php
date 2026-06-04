<?php

namespace App\Console\Commands;

use App\Models\CustomerCommunicationCampaign;
use App\Services\BusinessSmsService;
use Illuminate\Console\Command;

class ProcessScheduledCommunications extends Command
{
    protected $signature = 'communications:process-scheduled';

    protected $description = 'Send due scheduled customer communication campaigns';

    public function handle(BusinessSmsService $smsService): int
    {
        $processed = 0;

        CustomerCommunicationCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->with(['business.plan', 'user'])
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get()
            ->each(function (CustomerCommunicationCampaign $campaign) use ($smsService, &$processed) {
                $campaign->update(['status' => 'processing']);

                $result = $smsService->processCampaign($campaign);

                $campaign->update([
                    'status' => ($result['sent'] ?? 0) > 0 ? 'completed' : 'failed',
                    'result_summary' => $result,
                    'sent_at' => now(),
                ]);

                $processed++;
            });

        if ($processed > 0) {
            $this->info("Processed {$processed} scheduled campaign(s).");
        }

        return self::SUCCESS;
    }
}
