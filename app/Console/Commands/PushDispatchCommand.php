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
                ->where(function ($query) {
                    $query->where(function ($due) {
                        $due->where('status', 'scheduled')
                            ->where(function ($ready) {
                                $ready->whereNull('scheduled_at')
                                    ->orWhere('scheduled_at', '<=', now());
                            })
                            ->where(function ($notExpired) {
                                $notExpired->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    })->orWhere(function ($stale) {
                        // A send whose worker died or restarted is resumed, but
                        // never while a live dispatch may still be running: the
                        // service stamps last_dispatch_at when it starts (and
                        // when a job is handed to the queue).
                        $stale->where('status', 'sending')
                            ->where(function ($old) {
                                $old->whereNull('last_dispatch_at')
                                    ->orWhere('last_dispatch_at', '<', now()->subMinutes(10));
                            });
                    });
                })
                ->get();
        }

        foreach ($notifications as $notification) {
            $staged = $service->stageDeliveries($notification);
            $retryable = $service->retryableDeliveries($notification);

            if ($staged === 0 && $retryable === 0) {
                if ($notification->deliveries()->exists()) {
                    // Every attempt is spent or already accepted: record the
                    // truthful terminal state instead of re-queueing forever.
                    $service->settle($notification);
                    $this->line("[{$notification->id}] nothing left to send: {$notification->title}");
                } else {
                    // Nothing was ever staged — no eligible subscribers or the
                    // category is not push-eligible. Return it to the admin.
                    $notification->update(['status' => 'draft']);
                    $this->line("[{$notification->id}] no eligible subscriptions (or its category is not push-eligible); returned to draft: {$notification->title}");
                }

                continue;
            }

            $this->line("[{$notification->id}] staging {$staged} delivery(-ies), {$retryable} retryable: {$notification->title}");

            // Mark the run before handing it off so a concurrent cron pass can
            // never pick the same notification twice.
            $notification->update(['status' => 'sending', 'last_dispatch_at' => now()]);

            if (config('push.queue')) {
                DispatchPushNotification::dispatch($notification->fresh());

                $this->line("[{$notification->id}] handed to the queue.");
            } else {
                // Sync path: the job's work is done directly so the same
                // guarantee applies without a worker.
                $result = $service->dispatch($notification->fresh());

                $this->line("[{$notification->id}] sent {$result['sent']}, failed {$result['failed']}, disabled {$result['disabled']}.");
            }
        }

        $this->info(count($notifications).' notification(s) processed.');

        return self::SUCCESS;
    }
}
