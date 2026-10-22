<?php

namespace App\Jobs;

use App\Models\PushNotification;
use App\Services\Push\PushDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One dispatch attempt for a push notification.
 *
 * Queued when a queue driver is configured; with the sync driver it runs
 * inline, so scheduling never silently depends on the unverified production
 * worker (the standing S9 item) — the cron command runs the same service.
 */
class DispatchPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public PushNotification $notification)
    {
        // Only pin a named queue when one is configured; otherwise the
        // notification rides the application's default queue so a standard
        // worker picks it up.
        if ($queue = config('push.queue')) {
            $this->onQueue($queue);
        }
    }

    public function handle(PushDispatchService $service): void
    {
        // Re-read from storage: a scheduled row may have been cancelled or
        // withdrawn between staging and this job's execution. Any status that
        // can actually be dispatched (scheduled/sending/failed) proceeds.
        $notification = PushNotification::find($this->notification->id);

        if ($notification === null || in_array($notification->status, ['draft', 'cancelled'], true)) {
            return;
        }

        $service->dispatch($notification);

        // Fill the campaign ↔ notification link only once the notification
        // has actually been dispatched (never before).
        if ($notification->ad_campaign_id !== null) {
            $campaign = \App\Models\AdCampaign::find($notification->ad_campaign_id);

            if ($campaign !== null) {
                $campaign->update(['push_campaign_id' => $notification->id]);
            }
        }
    }
}
