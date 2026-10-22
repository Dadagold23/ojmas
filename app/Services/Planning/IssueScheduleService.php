<?php

namespace App\Services\Planning;

use App\Models\AdCampaign;
use App\Models\AdPlacement;
use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\IssueSchedule;
use App\Models\PushCategorySetting;
use App\Models\PushNotification;
use App\Models\Volume;
use App\Services\Advertising\AdCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Addendum Module 5 — automated issue and announcement scheduling.
 *
 * The binding rules this service enforces (the brief's hard constraints):
 *  - An announcement is prepared and published ONLY as an announcement:
 *    a scheduled ad-campaign window (Module 2 surfaces it when its window
 *    opens) and/or a scheduled push notification (Module 3 sends it when
 *    its time arrives). No code path here ever inserts an issue record as
 *    `published` or moves it, and no article is ever attached, moved or
 *    published by scheduling.
 *  - Existing issue records are reused: volume+number is matched first and
 *    a schedule is refused for a published issue; a genuinely future
 *    volume/issue is created in `draft` and left to the editors.
 *  - One schedule per issue (unique) and one announcement surface per
 *    schedule, so pausing, resuming, cancelling and re-scheduling can
 *    never strand an orphan or double-send anything.
 */
class IssueScheduleService
{
    public function __construct(private AdCampaignService $campaigns) {}

    /**
     * Creates (or extends) the plan. Reuses an existing volume and issue
     * when they exist; otherwise creates them in draft — visible nowhere
     * and publish by nobody.
     *
     * @return IssueSchedule The schedule in its initial state.
     */
    public function plan(array $data, ?Request $context = null): IssueSchedule
    {
        [$volume, $createdVolume] = $this->resolveVolume($data);
        [$issue, $createdIssue] = $this->resolveIssue($data, $volume);

        // Out of scope either way: an issue marked published, or one whose
        // publication date is already recorded — the announcement tools plan
        // the future, they do not re-announce what already happened.
        if ($issue->status === 'published' || $issue->published_on !== null) {
            abort(422, 'That issue is already published — schedules only plan future issues.');
        }

        if (IssueSchedule::where('issue_id', $issue->id)->exists()) {
            abort(422, 'This issue already has a schedule — open it to adjust the plan instead of duplicating.');
        }

        $wantsAnnouncement = ($data['submit_action'] ?? '') === 'schedule';

        $schedule = IssueSchedule::create(array_merge($this->payload($data), [
            'volume_id' => $volume->id,
            'issue_id' => $issue->id,
        ]));

        if ($createdVolume) {
            $this->audit($context, $volume, 'scheduling.volume.created');
        }
        if ($createdIssue) {
            $this->audit($context, $issue, 'scheduling.issue.placeholder_created');
        }
        $this->audit($context, $schedule, 'scheduling.schedule.created');

        if ($wantsAnnouncement) {
            $schedule = $this->publish($schedule->fresh(), $context);
        }

        return $schedule->fresh();
    }

    /**
     * Applies an edit and propagates every still-reversible consequence.
     * An announcement that is already live or already sent stays untouched:
     * a moving start window or an un-sent webhook does not exist. The
     * screen states exactly which consequences took effect.
     */
    public function updatePlan(IssueSchedule $schedule, array $data, ?Request $context = null): array
    {
        /** What downstream consequences happened (for the honest response). */
        $effect = ['campaign_updated' => false, 'notification_updated' => false, 'delivery_untouched' => false];
        $original = $schedule->only([
            'title', 'message', 'planned_publication_date', 'cfp_opens_at',
            'submission_deadline', 'announce_at', 'announcement_ends_at', 'landing_url',
        ]);

        $payload = $this->payload($data);

        $campaign = $schedule->campaign;
        if ($campaign !== null && in_array($campaign->status, ['draft', 'scheduled'], true)) {
            $campaign->update(array_filter([
                'title' => $payload['title'] ?? null,
                'description' => $payload['message'] ?? null,
                'starts_at' => $payload['announce_at'] ?? null,
                'ends_at' => $payload['announcement_ends_at'] ?? null,
                'destination_url' => $payload['landing_url'] ?? null,
            ], fn ($value) => $value !== null));
            $effect['campaign_updated'] = true;
        } elseif ($campaign !== null) {
            $effect['delivery_untouched'] = true;
        }

        $notification = $schedule->notification;
        if ($notification !== null && in_array($notification->status, ['draft', 'scheduled'], true)) {
            $notification->update(array_filter([
                'title' => $payload['title'] ?? null,
                'message' => $payload['message'] ?? null,
                'scheduled_at' => $payload['announce_at'] ?? null,
                'action_url' => $payload['landing_url'] ?? null,
            ], fn ($value) => $value !== null));
            $effect['notification_updated'] = true;
        } elseif ($notification !== null) {
            $effect['delivery_untouched'] = true;
        }

        $schedule->update($payload);
        $this->audit($context, $schedule->fresh(), 'scheduling.schedule.updated', [
            'old' => $original,
            'new' => $schedule->only(array_keys($original)),
            'effects' => $effect,
        ]);

        return $effect;
    }

    /**
     * Puts the announcement on hold: the campaign stops serving, and the
     * queued notification is cancelled — notifications that have already
     * left cannot be recalled, and the screen says so.
     */
    public function pause(IssueSchedule $schedule, ?Request $context = null): void
    {
        if (! in_array($schedule->status, [IssueSchedule::STATUS_SCHEDULED, IssueSchedule::STATUS_DRAFT], true)) {
            abort(422, 'Only a scheduled announcement can be paused.');
        }

        $campaign = $schedule->campaign;
        if ($campaign !== null && $campaign->status === 'scheduled') {
            $campaign->update(['status' => 'paused']);
        }

        $notification = $schedule->notification;
        if ($notification !== null && $notification->status === 'scheduled') {
            $notification->update(['status' => 'cancelled']);
        }

        $schedule->update(['status' => IssueSchedule::STATUS_PAUSED]);
        $this->audit($context, $schedule->fresh(), 'scheduling.schedule.paused');
    }

    /**
     * Resumes a paused announcement. A cancelled queued notification cannot
     * revive (its status is part of the recorded history), so a fresh,
     * correctly-bound notification is created — a never-sent cancelled one
     * cannot duplicate anything.
     */
    public function resume(IssueSchedule $schedule, ?Request $context = null): void
    {
        if ($schedule->status !== IssueSchedule::STATUS_PAUSED) {
            abort(422, 'Only a paused announcement can be resumed.');
        }
        if ($schedule->announce_at === null) {
            abort(422, 'The announcement needs its publication time before it can be resumed.');
        }

        $campaign = $schedule->campaign;
        if ($campaign !== null && $campaign->status === 'paused') {
            $this->campaigns->transition($campaign, 'published', auth()->id() ?? 0);
        }

        $notification = $schedule->notification;
        if ($schedule->notify_push && ($notification === null || $notification->status !== 'scheduled')) {
            $replacement = $this->notificationFor($schedule);
            $replacement->update(['status' => 'scheduled', 'scheduled_at' => $schedule->announce_at]);
            $notification = $replacement;
        }

        $schedule->update([
            'status' => IssueSchedule::STATUS_SCHEDULED,
            'linked_notification_id' => $notification?->id,
        ]);

        $this->audit($context, $schedule->fresh(), 'scheduling.schedule.resumed');
    }

    /**
     * Cancels the plan together with every queued surface. Records are
     * archived/cancelled, never deleted.
     */
    public function cancel(IssueSchedule $schedule, ?Request $context = null, string $reason = ''): void
    {
        if (in_array($schedule->status, [IssueSchedule::STATUS_CANCELLED], true)) {
            abort(422, 'The schedule is already cancelled.');
        }

        $campaign = $schedule->campaign;
        if ($campaign !== null && $this->campaigns->canTransition($campaign, 'archived')) {
            $this->campaigns->transition($campaign, 'archived', auth()->id() ?? 0);
        }

        $notification = $schedule->notification;
        if ($notification !== null && $notification->status === 'scheduled') {
            $notification->update(['status' => 'cancelled']);
            $this->audit($context, $notification->fresh(), 'push.notification.cancelled', ['reason' => $reason ?: 'issue schedule cancelled']);
        }

        $schedule->update(['status' => IssueSchedule::STATUS_CANCELLED]);
        $this->audit($context, $schedule->fresh(), 'scheduling.schedule.cancelled', ['reason' => $reason]);
    }

    /**
     * Turns a plan that was kept as draft into a real scheduled
     * announcement: the campaign window (which the Module 2 placement logic
     * serves exactly on time) and — where consented and eligible — the push
     * notification (which Module 3 dispatches at its recorded time).
     */
    public function publish(IssueSchedule $schedule, ?Request $context = null, ?string $announceAt = null): IssueSchedule
    {
        if (in_array($schedule->status, [IssueSchedule::STATUS_CANCELLED], true)) {
            abort(422, 'A cancelled schedule cannot be published.');
        }

        // Scheduling requires a publication time the window can start at.
        $announceAt = $announceAt ?? $schedule->announce_at?->format('Y-m-d\TH:i');
        if ($announceAt === null) {
            abort(422, 'Announcing an issue needs its announcement time.');
        }

        $campaign = $schedule->campaign;
        if ($campaign === null) {
            $campaign = AdCampaign::create([
                'placement_id' => $this->placementFor($schedule->announcement_type)?->id,
                'created_by' => $schedule->created_by,
                'title' => $schedule->title,
                'description' => $schedule->message,
                'type' => $schedule->announcement_type === 'call_for_papers'
                    ? 'call_for_papers' : 'issue_announcement',
                'advertiser_name' => null, // journal announcements are not third-party advertising
                'media_path' => $schedule->banner_path,
                'media_type' => $schedule->banner_path ? 'image' : null,
                'destination_url' => $schedule->landing_url,
                'starts_at' => $announceAt,
                'ends_at' => $schedule->announcement_ends_at,
                'status' => 'scheduled',
                'audience' => 'public',
                'notify_push' => false,
                'label' => $schedule->announcement_type === 'call_for_papers'
                    ? 'Call for Papers' : 'Announcement',
            ]);
            $schedule->update(['linked_campaign_id' => $campaign->id]);
        } elseif ($campaign->status === 'paused') {
            $this->campaigns->transition($campaign, 'published', auth()->id() ?? 0);
        }

        $notification = $schedule->notification;
        if ($schedule->notify_push && ($notification === null || $notification->status !== 'scheduled')) {
            // Only with the category's push eligibility on record: Module 3
            // sets category eligibility by hand on the eligibility screens.
            // An ineligible category never gets a silent queue entry either —
            // the schedule itself still runs, and its screen reports why no
            // notification exists.
            if (PushCategorySetting::where('category', $schedule->pushCategory())->where('is_eligible', true)->doesntExist()) {
                $this->audit($context, $schedule->fresh(), 'scheduling.push.skipped_ineligible', [
                    'category' => $schedule->pushCategory(),
                ]);
            } else {
                $notification = $this->notificationFor($schedule);
                $notification->update([
                    'status' => 'scheduled',
                    'scheduled_at' => $announceAt,
                ]);
                $schedule->update(['linked_notification_id' => $notification->id]);
            }
        }

        $schedule->update([
            'status' => IssueSchedule::STATUS_SCHEDULED,
            'announce_at' => $announceAt,
        ]);

        $this->audit($context, $schedule->fresh(), 'scheduling.schedule.published');
        $this->audit($context, $campaign->fresh(), 'scheduling.announcement.created');

        return $schedule->fresh();
    }

    /* ─────────────── helpers ─────────────── */

    /**
     * Reuses an existing volume (`volume_id`) or the volume matching an
     * entered year+number; only a genuinely new combination is created, and
     * it is created in draft — a planned volume is not a published one.
     *
     * @return array{0: \App\Models\Volume, 1: bool}
     */
    private function resolveVolume(array $data): array
    {
        if (! empty($data['volume_id'])) {
            return [Volume::findOrFail($data['volume_id']), false];
        }

        $year = (int) ($data['new_volume_year'] ?? now()->year);
        $no = (int) ($data['new_volume_no'] ?? 0);

        if ($no < 1) {
            abort(422, 'A schedule is anchored on a volume: pick an existing one or record the new volume number.');
        }

        $existing = Volume::where('volume_no', $no)->where('year', $year)->first();
        if ($existing !== null) {
            return [$existing, false];
        }

        $volume = Volume::create([
            'title' => 'Volume '.$no,
            'year' => $year,
            'volume_no' => $no,
            'status' => 'draft',
            'slug' => Str::slug('volume-'.$no.'-'.$year).'-'.Str::lower(Str::random(4)),
        ]);

        return [$volume, true];
    }

    /**
     * Reuses the volume's issue at the entered number — never creates a
     * duplicate — or creates a leakage-free placeholder issue in draft for
     * the editors to fill later.
     *
     * @return array{0: \App\Models\Issue, 1: bool}
     */
    private function resolveIssue(array $data, Volume $volume): array
    {
        if (! empty($data['issue_id'])) {
            return [Issue::findOrFail($data['issue_id']), false];
        }

        $no = (string) ($data['issue_no'] ?? '');
        if ($no === '') {
            abort(422, 'A schedule is anchored on an issue: record the issue number.');
        }

        $existing = Issue::where('volume_id', $volume->id)->where('issue_no', $no)->first();
        if ($existing !== null) {
            return [$existing, false];
        }

        // Issue numbers are free-form in the editorial tools (mostly numeric,
        // sometimes "3-4"), so the entered number is stored as the editors
        // would write it — matching works on the exact value they use.
        $issue = Issue::create([
            'volume_id' => $volume->id,
            'issue_no' => $no,
            'title' => 'Volume '.($volume->volume_no ?? $volume->id).' · Issue '.$no.' (planned)',
            'status' => 'draft',
            'description' => 'Created as a placeholder while planning this issue announcement; editors will complete it.',
            'slug' => Str::slug('volume-'.($volume->volume_no ?? $volume->id).'-issue-'.$no).'-'.Str::lower(Str::random(4)),
        ]);

        return [$issue, true];
    }

    private function payload(array $data): array
    {
        return collect($data)->only([
            'title', 'message', 'announcement_type', 'planned_publication_date',
            'cfp_opens_at', 'submission_deadline', 'announce_at',
            'announcement_ends_at', 'landing_url', 'banner_path',
            'notify_push', 'created_by',
        ])->all();
    }

    private function placementFor(string $type): ?AdPlacement
    {
        $key = $type === 'call_for_papers' ? 'calls_for_papers' : 'announcement_strip';

        return AdPlacement::where('key', $key)->where('is_active', true)->first()
            ?? AdPlacement::where('key', 'announcement_strip')->where('is_active', true)->first();
    }

    /** Builds (as a draft) the push notification this schedule would queue. */
    public function notificationFor(IssueSchedule $schedule): PushNotification
    {
        return PushNotification::create([
            'created_by' => $schedule->created_by,
            'title' => $schedule->title,
            'message' => $schedule->message ?? '',
            'category' => $schedule->pushCategory(),
            'audience' => 'all',
            'action_url' => $schedule->landing_url,
            'icon_path' => $schedule->banner_path,
            'status' => 'draft',
            'ad_campaign_id' => $schedule->linked_campaign_id,
        ]);
    }

    private function audit(?Request $context, $model, string $event, array $values = []): void
    {
        // A compact, honest snapshot per audited model; explicit values win
        // where the caller has something specific to record.
        $snapshot = match (true) {
            $model instanceof Volume => $model->only(['title', 'year', 'volume_no', 'status']),
            $model instanceof Issue => $model->only(['volume_id', 'issue_no', 'title', 'status']),
            $model instanceof IssueSchedule => $model->only([
                'title', 'announcement_type', 'status', 'announce_at',
                'planned_publication_date', 'cfp_opens_at', 'submission_deadline',
            ]),
            $model instanceof PushNotification => $model->only(['title', 'status', 'scheduled_at']),
            default => [],
        };

        AuditLog::create([
            'user_id' => $context?->user()?->getAuthIdentifier(),
            'event' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'new_values' => $values === [] ? $snapshot : $values,
            'ip_address' => $context?->ip(),
            'user_agent' => $context?->userAgent(),
        ]);
    }
}
