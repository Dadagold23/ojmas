<?php

namespace Tests\Feature\Addendum;

use App\Models\AdCampaign;
use App\Models\Issue;
use App\Models\IssueSchedule;
use App\Models\PushCategorySetting;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Models\Volume;
use App\Models\User;
use App\Services\Permissions\PermissionRegistrar;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Addendum Module 5 — automated issue and announcement scheduling.
 *
 * The hard constraints under test:
 *  - scheduling NEVER publishes an issue or an article;
 *  - existing issue records are reused and duplicates are impossible;
 *  - announcements are prepared as real windows (Module 2) or scheduled
 *    pushes (Module 3), and pausing/resuming/cancelling never strands or
 *    double-sends a notification;
 *  - an already-sent notification cannot be un-sent, and the screens say so.
 */
class Module5IssueScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Managing Editor', 'Super Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        app(EditorialWorkflowService::class)->seed();
        app(PermissionRegistrar::class)->sync();

        config()->set('push.vapid.public_key', 'BPJcWzRrWWlcJNtULY4-PN9lXkGSwSSlhngQhZBAFSbmA3lb3Mcvp4OGW9psBI3YcKBGVO7ZInb7ZBgAxAKHnxc');
        config()->set('push.vapid.private_key', 'a-14-char-or-longer-private-key');
        config()->set('push.vapid.subject', 'mailto:ops@example.org');
    }

    /* ─────────────── fixtures ─────────────── */

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function admin(): User
    {
        return $this->userWithRole('Admin');
    }

    private function existingVolume(): Volume
    {
        return Volume::firstOrCreate(
            ['volume_no' => 8, 'year' => 2027],
            [
                'title' => 'Volume 8',
                'status' => 'draft',
                'slug' => 'test-volume-8-'.strtolower(Str::random(5)),
            ],
        );
    }

    private function existingIssue(?Volume $volume = null, array $attributes = []): Issue
    {
        $volume = $volume ?? $this->existingVolume();
        $issueNo = $attributes['issue_no'] ?? '2';
        unset($attributes['issue_no']);

        return Issue::firstOrCreate(
            ['volume_id' => $volume->id, 'issue_no' => $issueNo],
            array_merge([
                'title' => 'Volume 8 Issue '.$issueNo,
                'status' => 'draft',
                'slug' => 'test-volume-8-issue-'.$issueNo.'-'.strtolower(Str::random(5)),
            ], $attributes),
        );
    }

    private function validScheduleData(array $overrides = []): array
    {
        $volume = $this->existingVolume();

        return array_merge([
            'title' => 'Volume 8, Issue 2 — publication announced',
            'message' => 'Volume 8 Issue 2 opens for submissions on 1 February 2027.',
            'announcement_type' => 'issue_announcement',
            'volume_id' => $volume->id,
            'issue_no' => '2',
            'planned_publication_date' => '2027-03-01',
            'announce_at' => '2027-01-15T09:00',
            'announcement_ends_at' => '2027-03-05T09:00',
            'landing_url' => 'https://ojmas.example.org/call-for-papers',
            'submit_action' => 'schedule',
        ], $overrides);
    }

    private function storeSchedule(User $as, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as)->post(route('admin.schedules.store'), $data);
    }

    /* ─────────────── authorisation (§5) ─────────────── */

    public function test_guests_are_kept_out_of_the_scheduler(): void
    {
        $this->get(route('admin.schedules.index'))->assertRedirect('/login');
    }

    public function test_only_administration_holds_scheduling_permissions(): void
    {
        foreach (['Author', 'Editor', 'Reviewer', 'Managing Editor'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('admin.schedules.index'))
                ->assertForbidden();

            $this->actingAs($this->userWithRole($role))
                ->post(route('admin.schedules.store'), $this->validScheduleData())
                ->assertForbidden();
        }

        $this->actingAs($this->admin())->get(route('admin.schedules.index'))->assertOk();

        $super = $this->userWithRole('Super Admin');
        $this->actingAs($super)->get(route('admin.schedules.index'))->assertOk();
    }

    /* ─────────────── plan and reuse (§5 "use existing records") ─────────────── */

    public function test_an_existing_issue_is_reused_and_never_duplicated(): void
    {
        $issue = $this->existingIssue();

        $this->storeSchedule($this->admin(), $this->validScheduleData())->assertRedirect();

        $this->assertSame(1, Issue::count());
        $this->assertSame(1, Volume::count());
        $this->assertSame($issue->id, IssueSchedule::first()->issue_id);
    }

    public function test_a_future_volume_and_issue_are_created_as_placeholders_never_as_published(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['volume_id' => null, 'new_volume_no' => 9, 'new_volume_year' => 2028, 'issue_no' => '1']))
            ->assertRedirect();

        $volume = Volume::where('volume_no', 9)->first();
        $issue = $volume->issues()->where('issue_no', '1')->first();

        $this->assertNotNull($volume);
        $this->assertNotNull($issue);
        $this->assertSame('draft', $volume->status);
        $this->assertSame('draft', $issue->status);
        $this->assertNull($issue->published_on);

        $this->assertStringContainsStringIgnoringCase('(planned)', $issue->title);
    }

    public function test_an_issue_cannot_have_two_schedules(): void
    {
        $admin = $this->admin();
        $this->storeSchedule($admin, $this->validScheduleData())->assertRedirect();

        $this->storeSchedule($admin, $this->validScheduleData())->assertStatus(422);
        $this->assertSame(1, IssueSchedule::count());
    }

    public function test_a_published_issue_is_out_of_scope_for_scheduling(): void
    {
        $this->existingIssue(attributes: ['published_on' => '2026-05-01']);

        $this->storeSchedule($this->admin(), $this->validScheduleData())->assertStatus(422);
        $this->assertSame(0, IssueSchedule::count());
    }

    /* ─────────────── announcement surfaces (§5) ─────────────── */

    public function test_scheduling_prepares_a_campaign_window_that_serves_on_time(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData())->assertRedirect();

        $campaign = AdCampaign::first();
        $schedule = IssueSchedule::first();

        $this->assertNotNull($campaign);
        $this->assertSame($schedule->linked_campaign_id, $campaign->id);
        $this->assertSame('scheduled', $campaign->status);
        $this->assertSame('issue_announcement', $campaign->type);
        $this->assertSame('2027-01-15 09:00', $campaign->starts_at->format('Y-m-d H:i'));
        $this->assertSame('https://ojmas.example.org/call-for-papers', $campaign->destination_url);

        // Before the window: nothing is served yet, though the record is approved.
        $this->travelTo(Carbon::parse('2027-01-10 09:00'));
        $this->get('/')->assertOk()->assertDontSee('Volume 8, Issue 2');

        // Inside the window: the announcement is served exactly then.
        $this->travelTo(Carbon::parse('2027-01-16 09:00'));
        $this->get('/')->assertOk()->assertSee('Volume 8, Issue 2');

        // The announced issue itself remains what the editors left it.
        $this->travelTo(Carbon::parse('2027-01-16 09:00'));
        $this->assertSame('draft', $schedule->issue->fresh()->status);
        $this->assertNull($schedule->issue->fresh()->published_on);
    }

    public function test_a_call_for_papers_schedule_runs_on_the_calls_for_papers_page(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData([
            'announcement_type' => 'call_for_papers',
            'title' => 'Call for Papers — Volume 8',
            'cfp_opens_at' => '2027-02-01',
            'submission_deadline' => '2027-02-28',
        ]))->assertRedirect();

        $campaign = AdCampaign::first();
        $this->assertSame('call_for_papers', $campaign->type);
        $this->assertSame('calls_for_papers', $campaign->placement->key);
    }

    public function test_the_announcement_is_taken_down_when_its_window_ends(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData())->assertRedirect();

        $this->travelTo(Carbon::parse('2027-03-20 09:00'));
        $this->get('/')->assertOk()->assertDontSee('Volume 8, Issue 2');
    }

    /* ─────────────── queued notifications (§5 + Module 3 rules) ─────────────── */

    public function test_a_consenting_notification_is_queued_and_dispatched_only_when_due(): void
    {
        Queue::fake();
        config()->set('push.queue', 'push-test');

        PushSubscription::create([
            'user_id' => null,
            'endpoint_hash' => hash('sha256', 'https://fcm.test/sched'),
            'endpoint' => 'https://fcm.test/sched',
            'p256dh' => 'BeWq0P4GJX0fnN8name0P4GJX0fnN8nameN8name=',
            'auth' => 'aW1hZ2luZVZhbGlkQXV0aA==',
            'wants_editorial' => true,
            'wants_promotions' => false,
            'is_active' => true,
        ]);

        $this->storeSchedule($this->admin(), $this->validScheduleData(['notify_push' => '1']))->assertRedirect();

        $schedule = IssueSchedule::first();
        $notification = $schedule->notification;

        $this->assertNotNull($notification);
        $this->assertSame('scheduled', $notification->status);
        $this->assertSame('upcoming_issue', $notification->category);
        $this->assertSame('2027-01-15 09:00', $notification->scheduled_at->format('Y-m-d H:i'));

        // Not due yet: the cron processes nothing.
        $this->travelTo(Carbon::parse('2027-01-01 09:00'));
        $this->artisan('push:dispatch')->expectsOutput('0 notification(s) processed.');
        Queue::assertNothingPushed();

        // Due: handed to the dispatcher exactly once, and it is a
        // notification of its own — not an issue-publishing step.
        $this->travelTo(Carbon::parse('2027-01-15 09:01'));
        $this->artisan('push:dispatch')->assertSuccessful();
        Queue::assertPushed(\App\Jobs\DispatchPushNotification::class, 1);
    }

    public function test_an_ineligible_category_is_left_out_and_reported_not_failed_silently(): void
    {
        PushCategorySetting::where('category', 'upcoming_issue')->update(['is_eligible' => false]);

        $this->storeSchedule($this->admin(), $this->validScheduleData(['notify_push' => '1']))->assertRedirect();

        $schedule = IssueSchedule::first();
        $this->assertNull($schedule->notification);
        $this->assertSame('scheduled', $schedule->status); // the announcement itself still runs
        $this->assertSame(0, PushNotification::count());

        $this->actingAs($this->admin())
            ->get(route('admin.schedules.show', $schedule))
            ->assertOk()
            ->assertSee('not marked eligible');
    }

    /* ─────────────── pause, resume, cancel (§5 "delayed issue") ─────────────── */

    public function test_pausing_a_scheduled_announcement_stops_it_and_cancels_the_queue(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['notify_push' => '1']))->assertRedirect();

        $schedule = IssueSchedule::first();

        $this->actingAs($this->admin())
            ->post(route('admin.schedules.pause', $schedule))
            ->assertRedirect();

        $schedule = $schedule->fresh();
        $this->assertSame('paused', $schedule->status);
        $this->assertSame('paused', $schedule->campaign->status);
        $this->assertSame('cancelled', $schedule->notification->status);

        // A paused campaign never serves, even inside what was its window.
        $this->travelTo(Carbon::parse('2027-01-16 09:00'));
        $this->get('/')->assertOk()->assertDontSee('Volume 8, Issue 2');
    }

    public function test_a_notification_already_sent_cannot_be_recalled_by_pausing(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['notify_push' => '1']))->assertRedirect();

        $schedule = IssueSchedule::first();
        $notification = $schedule->notification;
        $notification->update(['status' => 'sent']);

        $this->actingAs($this->admin())
            ->post(route('admin.schedules.pause', $schedule))
            ->assertRedirect();

        $schedule = $schedule->fresh();
        $this->assertSame('paused', $schedule->status);
        $this->assertSame('sent', $schedule->notification->status);
        $this->assertSame('paused', $schedule->campaign->status);
    }

    public function test_resuming_revives_the_window_and_queues_a_fresh_notification(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['notify_push' => '1']))->assertRedirect();

        $schedule = IssueSchedule::first();
        $firstNotification = $schedule->notification;

        $this->actingAs($this->admin())->post(route('admin.schedules.pause', $schedule))->assertRedirect();
        $this->actingAs($this->admin())->post(route('admin.schedules.resume', $schedule))->assertRedirect();

        $schedule = $schedule->fresh();
        $this->assertSame('scheduled', $schedule->status);
        $this->assertSame('scheduled', $schedule->campaign->status);

        // A cancelled queue entry stays cancelled in the record; the resumed
        // plan queues a fresh one instead of resurrecting history.
        $this->assertSame('cancelled', $firstNotification->fresh()->status);
        $this->assertNotSame($firstNotification->id, $schedule->linked_notification_id);

        $replacement = $schedule->notification;
        $this->assertSame('scheduled', $replacement->status);
        $this->assertSame($replacement->id, $schedule->linked_notification_id);
        $this->assertSame(2, PushNotification::count());
    }

    public function test_cancelling_archives_the_announcement_and_cancels_the_queue_without_deleting(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['notify_push' => '1']))->assertRedirect();

        $schedule = IssueSchedule::first();
        $campaignId = $schedule->campaign->id;
        $notificationId = $schedule->notification->id;

        $this->actingAs($this->admin())
            ->post(route('admin.schedules.cancel', $schedule), ['reason' => 'Issue delayed to volume 9'])
            ->assertRedirect();

        $schedule = $schedule->fresh();
        $this->assertSame('cancelled', $schedule->status);
        $this->assertSame('archived', AdCampaign::find($campaignId)->status);
        $this->assertSame('cancelled', PushNotification::find($notificationId)->status);

        // Nothing was deleted.
        $this->assertDatabaseHas('issue_schedules', ['id' => $schedule->id]);
        $this->assertSame(1, AdCampaign::count());
        $this->assertSame(1, PushNotification::count());
    }

    /* ─────────────── rescheduling a delayed issue (§5) ─────────────── */

    public function test_rescheduling_propagates_to_still_reversible_surfaces(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['notify_push' => '1']))->assertRedirect();

        $schedule = IssueSchedule::first();

        $olderAnnounce = $schedule->announce_at;

        $this->actingAs($this->admin())
            ->post(route('admin.schedules.update', $schedule), $this->validScheduleData([
                'notify_push' => '1',
                'announce_at' => '2027-02-20T09:00',
                'announcement_ends_at' => '2027-04-01T09:00',
                'submit_action' => 'schedule',
            ]))
            ->assertRedirect();

        $schedule = $schedule->fresh();
        $this->assertNotSame($olderAnnounce->format('Y-m-d H:i'), $schedule->announce_at->format('Y-m-d H:i'));
        $this->assertSame('2027-02-20 09:00', $schedule->campaign->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2027-04-01 09:00', $schedule->campaign->ends_at->format('Y-m-d H:i'));
        $this->assertSame('2027-02-20 09:00', $schedule->notification->scheduled_at->format('Y-m-d H:i'));
    }

    public function test_edits_never_touch_an_announcement_already_served_or_a_notification_already_sent(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['notify_push' => '1']))->assertRedirect();

        $schedule = IssueSchedule::first();
        $schedule->campaign->update(['status' => 'published']);
        $schedule->notification->update(['status' => 'sent', 'sent_at' => now()]);

        $this->actingAs($this->admin())
            ->post(route('admin.schedules.update', $schedule), $this->validScheduleData([
                'notify_push' => '1',
                'announce_at' => '2027-02-20T09:00',
                'announcement_ends_at' => '2027-04-01T09:00',
                'submit_action' => 'schedule',
            ]))
            ->assertRedirect();

        $schedule = $schedule->fresh();
        // The live window and the sent notification are exactly as recorded.
        $this->assertSame('2027-01-15 09:00', $schedule->campaign->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2027-03-05 09:00', $schedule->campaign->ends_at->format('Y-m-d H:i'));
        $this->assertSame('2027-01-15 09:00', $schedule->notification->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('sent', $schedule->notification->status);
    }

    /* ─────────────── validation (§5 dates) ─────────────── */

    public function test_scheduling_requires_an_announcement_time(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['announce_at' => null]))
            ->assertSessionHasErrors('announce_at');

        $this->assertSame(0, IssueSchedule::count());
    }

    public function test_the_announcement_window_cannot_end_before_it_starts(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['announcement_ends_at' => '2027-01-01T09:00']))
            ->assertSessionHasErrors('announcement_ends_at');

        $this->assertSame(0, AdCampaign::count());
    }

    public function test_the_submission_deadline_cannot_precede_the_call_opening(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData([
            'announcement_type' => 'call_for_papers',
            'cfp_opens_at' => '2027-02-01',
            'submission_deadline' => '2027-01-01',
        ]))
            ->assertSessionHasErrors('submission_deadline');
    }

    public function test_non_web_addresses_are_refused_for_the_landing_page(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['landing_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('landing_url');
    }

    /* ─────────────── calendar view (§5) ─────────────── */

    public function test_the_calendar_shows_recorded_dates_and_only_their_month(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData())->assertRedirect();

        $this->travelTo(Carbon::parse('2027-01-10 09:00'));
        $this->actingAs($this->admin())
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('V8·Iss 2')
            ->assertSee('Volume 8, Issue 2');

        // The planned publication date is shown in its own month too.
        $this->actingAs($this->admin())
            ->get(route('admin.schedules.index', ['y' => 2027, 'm' => 3]))
            ->assertOk()
            ->assertSee('V8·Iss 2');
    }

    public function test_a_cancelled_schedule_leaves_the_calendar(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['announce_at' => '2027-01-05T09:00']))->assertRedirect();

        $schedule = IssueSchedule::first();
        $this->actingAs($this->admin())->post(route('admin.schedules.cancel', $schedule))->assertRedirect();

        $this->actingAs($this->admin())
            ->get(route('admin.schedules.index', ['y' => 2027, 'm' => 1]))
            ->assertOk()
            ->assertDontSee('V8·Iss 2');
    }

    /* ─────────────── audit trail (§5 + honesty) ─────────────── */

    public function test_every_planning_action_is_audited(): void
    {
        $this->storeSchedule($this->admin(), $this->validScheduleData(['notify_push' => '1']))->assertRedirect();

        $schedule = IssueSchedule::first();
        $this->actingAs($this->admin())->post(route('admin.schedules.pause', $schedule))->assertRedirect();
        $this->actingAs($this->admin())->post(route('admin.schedules.resume', $schedule))->assertRedirect();
        $this->actingAs($this->admin())->post(route('admin.schedules.cancel', $schedule), ['reason' => 'delayed'])->assertRedirect();

        $events = \App\Models\AuditLog::whereIn('event', [
            'scheduling.schedule.created', 'scheduling.schedule.paused',
            'scheduling.schedule.resumed', 'scheduling.schedule.cancelled',
            'scheduling.announcement.created',
        ])->pluck('event')->all();

        $this->assertSame([
            'scheduling.schedule.created',
            'scheduling.announcement.created',
            'scheduling.schedule.paused',
            'scheduling.schedule.resumed',
            'scheduling.schedule.cancelled',
        ], array_values($events));

        // Cancelled plans are kept, never deleted.
        $this->assertSame(1, IssueSchedule::count());
    }
}
