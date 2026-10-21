<?php

namespace App\Services\Advertising;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use Illuminate\Http\Request;

/**
 * Campaign lifecycle and public serving.
 *
 * Status rules are enforced here — never inferred from hidden UI state. The
 * visibility question ("may this appear on the public site right now?") is a
 * pure function of approved status + publication window, so scheduling and
 * expiry work without depending on an unverified cron worker.
 */
class AdCampaignService
{
    /** Allowed status transitions. */
    private const TRANSITIONS = [
        'draft' => ['pending', 'published', 'archived'],
        'pending' => ['draft', 'published', 'archived'],
        'scheduled' => ['published', 'paused', 'archived'],
        'published' => ['paused', 'archived'],
        'paused' => ['published', 'archived'],
        'expired' => ['archived'],
        'archived' => [],
    ];

    public function canTransition(AdCampaign $campaign, string $to): bool
    {
        if ($to === $campaign->status) {
            return false;
        }

        return in_array($to, self::TRANSITIONS[$campaign->status] ?? [], true);
    }

    /**
     * Moves a campaign to a new status after validation. When the target
     * status is publication-like but the window has not opened yet, the
     * campaign lands on `scheduled` — an approved state that starts serving
     * the moment the window opens.
     *
     * @return AdCampaign The refreshed campaign.
     */
    public function transition(AdCampaign $campaign, string $to, int $userId): AdCampaign
    {
        if (! in_array($to, array_keys(AdCampaign::STATUSES), true)) {
            abort(422, 'Unknown campaign status.');
        }

        if (! $this->canTransition($campaign, $to)) {
            abort(422, 'A campaign cannot move from '.$campaign->status.' to '.$to.'.');
        }

        if (in_array($to, ['published', 'scheduled'], true)) {
            if ($campaign->starts_at !== null && $campaign->starts_at->isFuture()) {
                $to = 'scheduled';
            } elseif ($campaign->ends_at !== null && $campaign->ends_at->isPast()) {
                // Approving a campaign whose window already closed archives it
                // rather than showing a dead advertisement.
                abort(422, 'The campaign window has already closed; archive it instead.');
            }
        }

        $campaign->update(['status' => $to]);

        return $campaign->fresh();
    }

    /**
     * Visible campaigns for a placement key, priority first. Impression
     * recording happens here so a rendered advertisement is exactly one
     * counted impression per visitor fingerprint per day.
     *
     * @return array<int, AdCampaign>
     */
    public function visibleForPlacement(string $placementKey, ?Request $request = null): array
    {
        $placement = \App\Models\AdPlacement::query()
            ->where('key', $placementKey)
            ->where('is_active', true)
            ->first();

        if ($placement === null) {
            return [];
        }

        $campaigns = $placement->campaigns()
            ->whereIn('status', ['published', 'scheduled'])
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (AdCampaign $campaign) => $campaign->windowIsOpen())
            ->values();

        if ($request !== null) {
            foreach ($campaigns as $campaign) {
                $this->recordEvent($campaign, AdEvent::IMPRESSION, $request);
            }
        }

        return $campaigns->all();
    }

    /**
     * Records an impression or click, deduplicated per visitor fingerprint
     * per day. Counted events are only those genuinely observed.
     */
    public function recordEvent(AdCampaign $campaign, string $event, Request $request): void
    {
        $fingerprint = $this->fingerprint($request);

        AdEvent::firstOrCreate([
            'campaign_id' => $campaign->id,
            'event' => $event,
            'fingerprint' => $fingerprint,
            'occurred_on' => now()->toDateString(),
        ], [
            'occurred_at' => now(),
        ]);
    }

    /**
     * A daily-rotating pseudonymous fingerprint: hashed IP + user agent +
     * date, salted. It can count unique visitors per day but cannot be
     * reversed into an IP address, and it stops matching after the day ends.
     */
    private function fingerprint(Request $request): string
    {
        return hash('sha256', implode('|', [
            config('app.key'),
            $request->ip() ?? 'unknown',
            $request->userAgent() ?? 'unknown',
            now()->toDateString(),
        ]));
    }
}
