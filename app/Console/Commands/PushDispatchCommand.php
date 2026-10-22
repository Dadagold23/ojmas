<?php

namespace App\Console\Commands;

use App\Jobs\DispatchPushNotification;
use App\Models\PushNotification;
use App\Services\Push\PushDispatchService;
use Illuminate\Console\Command;

/**
 * The scheduler entry point for push notifications (host cron drives it).
 *
 * Every due scheduled notification — subject to its optional expiry — is
 * staged to its audience and handed to the queue (sync driver: inline).
 * Duplicate sends are impossible: staging and the delivery table both dedupe.
 */
class PushDispatchCommand extends Command
{
    protected $signature = 'push:dispatch {notification? : Send a specific notification id now}';

    protected $description = 'Stage and dispatch due push notifications (runs from host cron)';

    public function handle(PushDispatchService $service): int
    {
        if ($id = $this->argument('notification')) {
            $notifications = PushNotification::query()
                ->whereIn('status', ['scheduled', 'sending', 'failed'])
                ->whereKey((int) $id)
                ->get();
        } else {
            $notifications = PushNotification::query()
                ->where('status', 'sending')
                ->orWhere(function ($query) {
                    $query->where('status', 'scheduled')
                        ->where(function ($due) {
                            $due->whereNull('scheduled_at')
                                ->orWhere('scheduled_at', '<=', now());
                        })
                        ->where(function ($notExpired) {
                            $notExpired->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                })
                ->get();
        }

        foreach ($notifications as $notification) {
            $staged = $service->stageDeliveries($notification);

            if ($staged === 0) {
                $this->line("[{$notification->id}] no eligible subscriptions (or its category is not push-eligible): {$notification->title}");

                // The notification was queued to its full audience once before
                // — it must not be re-staged forever, nor left 'sending'.
                continue;
            }

            $this->line("[{$notification->id}] staging {$staged} delivery(-ies): {$notification->title}");

            if (config('push.queue')) {
                DispatchPushNotification::dispatch($notification->fresh());

                $this->line("[{$notification->id}] handed to the queue.");
            } else {
                // Sync path: the job's work is done directly so the same
                // guarantee applies without a worker.
                $notification->fresh()->update(['status' => 'sending']);
                $result = $service->dispatch($notification->fresh());

                $this->line("[{$notification->id}] sent {$result['sent']}, failed {$result['failed']}, disabled {$result['disabled']}.");
            }
        }

        $this->info(count($notifications).' notification(s) processed.');

        return self::SUCCESS;
    }
}
