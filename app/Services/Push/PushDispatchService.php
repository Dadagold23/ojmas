<?php

namespace App\Services\Push;

use App\Models\PushCategorySetting;
use App\Models\PushDelivery;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Web Push delivery — Addendum Module 3.
 *
 * Delivery is honest: only what the push service can actually observe is
 * recorded (HTTP-level acceptance per endpoint). There is no read/receipt
 * channel in Web Push, so none is claimed anywhere.
 */
class PushDispatchService
{
    /**
     * Subscriptions matching the notification's audience consent or role.
     *
     * §3.6: consent-based targeting and a hard separation between editorial
     * and promotional consent — promotions are never sent to subscribers
     * who did not opt in.
     *
     * @return array<int, PushSubscription>
     */
    public function subscriptionsFor(PushNotification $notification): array
    {
        $query = PushSubscription::query()
            ->where('is_active', true);

        switch ($notification->audience) {
            case 'editorial':
                // Only subscribers who consented to editorial updates.
                return $query->where('wants_editorial', true)->get()->all();
            case 'promotions':
                return $query->where('wants_promotions', true)->get()->all();
            case 'authors':
                return $query->where('user_id', '<>', 0)
                    ->get()
                    ->filter(fn (PushSubscription $s) => $s->user?->hasRole('Author'))
                    ->values()
                    ->all();
            case 'reviewers':
                return $query->where('user_id', '<>', 0)
                    ->get()
                    ->filter(fn (PushSubscription $s) => $s->user?->hasRole('Reviewer'))
                    ->values()
                    ->all();
            case 'editors':
                return $query->where('user_id', '<>', 0)
                    ->get()
                    ->filter(fn (PushSubscription $s) => $s->user?->hasRole('Editor')
                        || $s->user?->hasRole('Admin')
                        || $s->user?->hasRole('Super Admin'))
                    ->values()
                    ->all();
            default: // 'all'
                return $query->get()->all();
        }
    }

    /**
     * Runs one dispatch attempt for a notification: ensures exactly one
     * pending delivery row per eligible subscription, then sends.
     *
     * @return array{pending_found: int, sent: int, failed: int, disabled: int}
     */
    public function dispatch(PushNotification $notification, ?WebPush $webPush = null): array
    {
        $webPush = $webPush ?? $this->buildWebPush();

        $notification->update([
            'status' => 'sending',
            'last_dispatch_at' => now(),
        ]);

        $deliveries = PushDelivery::query()
            ->with('subscription')
            ->where('notification_id', $notification->id)
            ->get();

        $sent = 0;
        $failedCount = 0;
        $disabled = 0;
        $maxAttempts = (int) config('push.max_attempts', 3);

        foreach ($deliveries as $delivery) {
            $delivery->refresh();

            if ($delivery->status === PushDelivery::SENT) {
                continue; // duplicate-send protection
            }

            if ($delivery->attempts >= $maxAttempts) {
                continue; // retry policy: stops after the recorded limit
            }

            $subscription = $delivery->subscription;
            if ($subscription === null || ! $subscription->isDeliverable()) {
                $delivery->update([
                    'status' => PushDelivery::FAILED,
                    'last_error' => 'Subscription is no longer deliverable.',
                ]);
                continue;
            }

            $delivery->increment('attempts');

            try {
                $webPush->queueNotification(
                    $this->subscriptionFor($subscription),
                    $this->payload($notification)
                );

                $reports = $webPush->flush($this->batchSize());
                $reportList = $reports !== null ? iterator_to_array($reports) : [];
                $report = $reportList[0] ?? null;

                if ($report !== null && $report->isSuccess()) {
                    $sent++;
                    $delivery->update([
                        'status' => PushDelivery::SENT,
                        'delivered_at' => now(),
                        'last_error' => null,
                    ]);
                    $subscription->forceFill([
                        'failure_count' => 0,
                        'last_success_at' => now(),
                        'last_error' => null,
                    ])->save();
                } else {
                    $statusCode = $report?->getResponse()?->getStatusCode();
                    $reason = $report?->getReason();

                    $delivery->update([
                        'status' => PushDelivery::FAILED,
                        'last_error' => filled($reason) ? $reason : 'Web push endpoint rejected the delivery.',
                    ]);

                    $subscription->forceFill([
                        'failure_count' => $subscription->failure_count + 1,
                        'last_error' => filled($reason) ? $reason : 'Web push endpoint rejected the delivery.',
                    ])->save();

                    if (
                        ($statusCode !== null && in_array($statusCode, [404, 410], true))
                        || $report?->isSubscriptionExpired()
                    ) {
                        // The browser unsubscribed or the endpoint is gone:
                        // the record is disabled, never deleted.
                        $subscription->update(['is_active' => false]);
                        $disabled++;
                    } elseif ($subscription->failure_count >= (int) config('push.max_consecutive_failures', 5)) {
                        $subscription->update(['is_active' => false]);
                        $disabled++;
                    }

                    $failedCount++;
                }
            } catch (\Throwable $e) {
                Log::warning('Push delivery exception', [
                    'notification_id' => $notification->id,
                    'delivery_id' => $delivery->id,
                    'error' => $e->getMessage(),
                ]);

                $delivery->update([
                    'status' => PushDelivery::FAILED,
                    'last_error' => 'Push delivery exception: '.$e->getMessage(),
                ]);

                $subscription->forceFill([
                    'failure_count' => $subscription->failure_count + 1,
                    'last_error' => 'Push delivery exception: '.$e->getMessage(),
                ])->save();

                if ($subscription->failure_count >= (int) config('push.max_consecutive_failures', 5)) {
                    $subscription->update(['is_active' => false]);
                    $disabled++;
                }

                $failedCount++;
            }
        }

        // Terminal status reflects the recorded deliveries, not just this
        // run's counters: pending work keeps it in flight; otherwise it is
        // honest about whether anything was accepted at all.
        $sentDeliveries = PushDelivery::query()
            ->where('notification_id', $notification->id)
            ->where('status', PushDelivery::SENT)
            ->count();

        $pendingDeliveries = PushDelivery::query()
            ->where('notification_id', $notification->id)
            ->where('status', PushDelivery::PENDING)
            ->count();

        if ($pendingDeliveries > 0) {
            $status = 'sending';
        } elseif ($sentDeliveries > 0) {
            $status = 'sent';
        } elseif ($failedCount > 0) {
            $status = 'failed';
        } else {
            $status = 'sent';
        }

        $notification->update([
            'status' => $status,
            'sent_at' => $pendingDeliveries > 0 ? $notification->sent_at : now(),
            'dispatch_attempts' => $notification->dispatch_attempts + 1,
        ]);

        return [
            'sent' => $sent,
            'failed' => $failedCount,
            'disabled' => $disabled,
            'deliveries' => $deliveries->count(),
        ];
    }

    /**
     * Creates the checkout of delivery rows for a notification that has not
     * been dispatched yet (idempotent: unique per pair).
     *
     * @return int Number of eligible subscriptions staged.
     */
    public function stageDeliveries(PushNotification $notification): int
    {
        // Category eligibility is an administrator setting (§3.2): a non-
        // eligible category cannot enter public dispatch.
        $eligible = PushCategorySetting::query()
            ->where('category', $notification->category)
            ->where('is_eligible', true)
            ->exists();

        if (! $eligible) {
            return 0;
        }

        $staged = 0;

        foreach ($this->subscriptionsFor($notification) as $subscription) {
            $delivery = PushDelivery::firstOrCreate([
                'notification_id' => $notification->id,
                'subscription_id' => $subscription->id,
            ], [
                'status' => PushDelivery::PENDING,
            ]);

            // Only genuinely new rows count as staged work; the cron's
            // "no eligible subscriptions" decision depends on this.
            if ($delivery->wasRecentlyCreated) {
                $staged++;
            }
        }

        return $staged;
    }

    private function buildWebPush(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => config('push.vapid.subject'),
                'publicKey' => config('push.vapid.public_key'),
                'privateKey' => config('push.vapid.private_key'),
            ],
        ]);
    }

    private function batchSize(): int
    {
        return 250;
    }

    private function subscriptionFor(PushSubscription $s): Subscription
    {
        return new Subscription($s->endpoint, $s->p256dh, $s->auth);
    }

    private function payload(PushNotification $n): string
    {
        $array = array_filter([
            'title' => $n->title,
            'body' => $n->message,
            'icon' => $n->icon_path !== null
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($n->icon_path)
                : null,
            'url' => $n->action_url,
            'tag' => 'ojmas-'.$n->category,
            'category' => $n->category,
        ], fn ($v) => $v !== null);

        return json_encode($array) ?: '{}';
    }
}
