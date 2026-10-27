<?php

namespace Tests\Feature\Addendum;

use App\Jobs\DispatchPushNotification;
use App\Models\AuditLog;
use App\Models\PushCategorySetting;
use App\Models\PushDelivery;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Permissions\PermissionRegistrar;
use App\Services\Push\PushDispatchService;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Addendum Module 3 — Web Push Notification System.
 *
 * Consent-based subscriptions, honest delivery recording, duplicate-send
 * protection, invalid-subscription disabling and administrator control over
 * category eligibility. Dispatch tests drive a stubbed WebPush, so nothing
 * here depends on real browser endpoints being reachable.
 */
class Module3WebPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Managing Editor', 'Super Admin', 'Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

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
        // Keep the legacy role column consistent with the Spatie role — it
        // otherwise defaults to 'author' and skews role-based audiences.
        $user->syncLegacyRoleColumn($role);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    private function subscription(array $attributes = []): PushSubscription
    {
        $endpoint = $attributes['endpoint']
            ?? 'https://fcm.test/'.strtolower(\Illuminate\Support\Str::random(10));

        return PushSubscription::create(array_merge([
            'user_id' => null,
            'endpoint_hash' => hash('sha256', $endpoint),
            'endpoint' => $endpoint,
            'p256dh' => 'BeWq0P4GJX0fnN8name0P4GJX0fnN8nameN8name=',
            'auth' => 'aW1hZ2luZVZhbGlkQXV0aA==',
            'wants_editorial' => true,
            'wants_promotions' => false,
            'is_active' => true,
        ], $attributes));
    }

    private function notification(array $attributes = []): PushNotification
    {
        return PushNotification::create(array_merge([
            'created_by' => $this->userWithRole('Admin')->id,
            'title' => 'A Test Notification',
            'message' => 'Short recorded message.',
            'category' => 'new_issue',
            'audience' => 'all',
            'status' => 'draft',
        ], $attributes));
    }

    /** A WebPush stub whose queued notifications all succeed or fail by code. */
    private function stubWebPush(int $statusCode, int ...$moreCodes): WebPush
    {
        $codes = [$statusCode, ...$moreCodes];
        $webPush = $this->createStub(WebPush::class);

        $webPush->method('queueNotification')->willReturnCallback(function () {});

        $webPush->method('flush')->willReturnCallback(function () use (&$codes) {
            $code = array_shift($codes) ?? 200;
            $request = new \GuzzleHttp\Psr7\Request('POST', 'https://fcm.test/push');
            $report = new MessageSentReport($request, new \GuzzleHttp\Psr7\Response($code), $code < 300, $code < 300 ? 'OK' : 'endpoint responded '.$code);

            yield from [$report];
        });

        return $webPush;
    }

    /* ─────────────── public subscription (§3.4) ─────────────── */

    public function test_anonymous_visitors_may_subscribe_after_explicit_action(): void
    {
        // The real browser does the prompting; this endpoint only ever sees a
        // subscription that was granted. Data recorded is what the standard
        // requires — nothing personal.
        $this->post(route('push.subscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/anonymous-1',
            'p256dh' => 'anonymous-p256dh',
            'auth' => 'anonymous-auth',
            'wants_promotions' => false,
        ])->assertOk()->assertJson(['ok' => true]);

        $record = PushSubscription::where('endpoint_hash', hash('sha256', 'https://fcm.googleapis.com/fcm/send/anonymous-1'))->firstOrFail();
        $this->assertFalse($record->wants_promotions); // never default-on
        $this->assertTrue($record->wants_editorial);
        $this->assertNull($record->user_id);
    }

    public function test_a_denied_or_malformed_subscription_is_refused(): void
    {
        $this->post(route('push.subscribe'), [
            'endpoint' => 'javascript:alert(1)',
            'p256dh' => 'x',
            'auth' => 'y',
        ])->assertSessionHasErrors('endpoint');

        $this->assertNull(PushSubscription::first());
    }

    public function test_unsubscribe_disables_the_record_without_deleting_it(): void
    {
        $item = $this->subscription(['wants_promotions' => true]);

        $this->post(route('push.unsubscribe'), ['endpoint' => $item->endpoint])
            ->assertOk()->assertJson(['ok' => true]);

        $item->refresh();
        $this->assertFalse($item->is_active);
        $this->assertNotNull(PushSubscription::find($item->id)); // never deleted
    }

    public function test_resubscribing_the_same_endpoint_refreshes_not_duplicates(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/refresh-me';

        foreach ([false, true] as $promotions) {
            $this->post(route('push.subscribe'), [
                'endpoint' => $endpoint,
                'p256dh' => 'p256dh-refresh',
                'auth' => 'auth-refresh',
                'wants_promotions' => $promotions,
            ])->assertOk();
        }

        $this->assertSame(1, PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->count());

        $record = PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->first();
        $this->assertTrue($record->wants_promotions); // last consent wins
    }

    /* ─────────────── audience targeting (§3.6) ─────────────── */

    public function test_promotional_notifications_reach_only_opted_in_subscribers(): void
    {
        $optedIn = $this->subscription(['wants_promotions' => true]);
        $this->subscription(['wants_promotions' => false]); // never receives promotions

        $notification = $this->notification(['audience' => 'promotions', 'category' => 'advertisement_campaign']);
        $service = app(PushDispatchService::class);

        $service->stageDeliveries($notification);
        $service->dispatch($notification, $this->stubWebPush(201));

        $optedInDelivery = PushDelivery::where('notification_id', $notification->id)
            ->where('subscription_id', $optedIn->id)->first();
        $this->assertSame('sent', $optedInDelivery->status);

        $this->assertSame(1, PushDelivery::where('notification_id', $notification->id)->count());
    }

    public function test_role_audiences_target_authenticated_subscribers_only(): void
    {
        $author = $this->userWithRole('Author');
        $reviewer = $this->userWithRole('Reviewer');
        $editor = $this->userWithRole('Editor');

        $authorSub = $this->subscription(['user_id' => $author->id]);
        $reviewerSub = $this->subscription(['user_id' => $reviewer->id]);
        $editorSub = $this->subscription(['user_id' => $editor->id]);
        $this->subscription(); // anonymous: never receives role-targeted pushes

        $service = app(PushDispatchService::class);

        $authorsNotification = $this->notification(['audience' => 'authors']);
        $service->stageDeliveries($authorsNotification);
        $service->dispatch($authorsNotification, $this->stubWebPush(201, 201, 201));
        $this->assertSame(1, PushDelivery::where('notification_id', $authorsNotification->id)->count());
        $this->assertSame($authorSub->id, PushDelivery::where('notification_id', $authorsNotification->id)->first()->subscription_id);

        $editorsNotification = $this->notification(['audience' => 'editors']);
        $service->stageDeliveries($editorsNotification);
        $service->dispatch($editorsNotification, $this->stubWebPush(201, 201, 201));
        $this->assertSame(1, PushDelivery::where('notification_id', $editorsNotification->id)->count());
        $this->assertSame($editorSub->id, PushDelivery::where('notification_id', $editorsNotification->id)->first()->subscription_id);
    }

    /* ─────────────── delivery honesty, retries, dedupe (§3.5) ─────────────── */

    public function test_dispatch_records_only_observable_delivery_results(): void
    {
        $a = $this->subscription();
        $b = $this->subscription();

        $notification = $this->notification();
        $service = app(PushDispatchService::class);

        // one accepted (201), one gone (410)
        $service->stageDeliveries($notification);
        $result = $service->dispatch(
            $notification,
            $this->stubWebPush(201, 410)
        );

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, $result['disabled']);

        $this->assertSame('sent', PushNotification::find($notification->id)->status);
        $this->assertNotNull(PushNotification::find($notification->id)->sent_at);

        $failed = PushDelivery::where('notification_id', $notification->id)
            ->where('status', 'failed')->firstOrFail();
        $this->assertTrue($failed->subscription->is_active === false); // 410 → disabled, not deleted
    }

    public function test_retries_have_a_limit_and_never_send_duplicates(): void
    {
        $item = $this->subscription();
        $notification = $this->notification();
        $service = app(PushDispatchService::class);

        // Every attempt fails with a server error (500): not a disable case.
        $service->stageDeliveries($notification);
        $service->dispatch($notification, $this->stubWebPush(500));
        $service->dispatch($notification->fresh(), $this->stubWebPush(500)); // failed stays retryable

        // Third run retries the (attempts < 3) delivery… then the limit holds.
        $service->dispatch($notification->fresh(), $this->stubWebPush(201));

        $delivery = PushDelivery::where('notification_id', $notification->id)->where('subscription_id', $item->id)->first();
        $this->assertSame(3, $delivery->attempts); // hard retry limit reached...

        // A further run must not increase attempts past the limit.
        $result = $service->dispatch($notification->fresh(), $this->stubWebPush(201));
        $this->assertSame(0, $result['sent']);

        $delivery->refresh();
        $this->assertSame(3, $delivery->attempts);
    }

    public function test_duplicate_staging_is_impossible(): void
    {
        $this->subscription();
        $this->subscription();

        $notification = $this->notification(['status' => 'scheduled']);
        $service = app(PushDispatchService::class);

        $first = $service->stageDeliveries($notification);
        $second = $service->stageDeliveries($notification);

        $this->assertSame(2, $first);
        $this->assertSame(0, $second); // the checkout already exists
        $this->assertSame(2, PushDelivery::where('notification_id', $notification->id)->count());
    }

    public function test_scheduled_notifications_dispatch_only_when_due_so_the_cron_cannot_double_send(): void
    {
        Queue::fake();
        config()->set('push.queue', 'push-test'); // route the staging through the queue

        $this->subscription();
        $notification = $this->notification(['status' => 'scheduled', 'scheduled_at' => now()->addDay()]);

        // Not due yet: nothing staged, nothing queued.
        $this->artisan('push:dispatch')->expectsOutput('0 notification(s) processed.');
        Queue::assertNothingPushed();

        // Due: staged and queue handed off exactly once.
        $notification->update(['scheduled_at' => now()->subMinute()]);
        $this->artisan('push:dispatch')->assertSuccessful();
        Queue::assertPushed(DispatchPushNotification::class, 1);
    }

    /* ─────────────── resuming interrupted sends (§3.5) ─────────────── */

    public function test_cron_resumes_a_failed_send_and_still_honours_the_attempt_limit(): void
    {
        $item = $this->subscription();

        $notification = $this->notification([
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
        ]);

        // One stub carries the whole sequence: the first run is rejected with
        // a server error, the resumed run is accepted.
        $service = new class($this->stubWebPush(500, 201)) extends PushDispatchService {
            public function __construct(private WebPush $stub) {}

            public function dispatch(PushNotification $notification, ?WebPush $webPush = null): array
            {
                return parent::dispatch($notification, $webPush ?? $this->stub);
            }
        };
        $this->instance(PushDispatchService::class, $service);

        $this->artisan('push:dispatch')->assertSuccessful();

        $delivery = PushDelivery::where('notification_id', $notification->id)
            ->where('subscription_id', $item->id)->firstOrFail();

        $this->assertSame(PushDelivery::FAILED, $delivery->status);
        $this->assertSame('failed', $notification->fresh()->status);
        $this->assertNull($notification->fresh()->sent_at);

        // Its delivery rows already exist, so only the retry-aware scheduler
        // can resume it (the old code skipped any notification with rows).
        $notification->refresh()->update(['status' => 'scheduled', 'scheduled_at' => now()]);

        $this->artisan('push:dispatch')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame(PushDelivery::SENT, $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        $this->assertSame('sent', $notification->fresh()->status);
        $this->assertNotNull($notification->fresh()->sent_at);
    }

    public function test_cron_settles_an_exhausted_scheduled_notification_instead_of_leaving_it_queued(): void
    {
        $this->subscription();
        $notification = $this->notification();
        $service = app(PushDispatchService::class);

        $service->stageDeliveries($notification);
        for ($i = 0; $i < 3; $i++) {
            $service->dispatch($notification->fresh(), $this->stubWebPush(500));
        }

        $notification->update(['status' => 'scheduled', 'scheduled_at' => now()]);

        $this->artisan('push:dispatch')->assertSuccessful();

        $this->assertSame('failed', $notification->fresh()->status);
    }

    public function test_an_exhausted_retry_never_relabels_the_notification_as_sent(): void
    {
        $this->subscription();
        $notification = $this->notification();
        $service = app(PushDispatchService::class);

        $service->stageDeliveries($notification);
        for ($i = 0; $i < 3; $i++) {
            $service->dispatch($notification->fresh(), $this->stubWebPush(500));
        }

        $this->assertSame('failed', $notification->fresh()->status);

        $result = $service->dispatch($notification->fresh(), $this->stubWebPush(201));

        $this->assertSame(0, $result['sent']);
        $this->assertSame('failed', $notification->fresh()->status);
        $this->assertNull($notification->fresh()->sent_at);
    }

    public function test_an_admin_retry_restores_the_attempt_budget_of_failed_deliveries(): void
    {
        $this->subscription();
        $notification = $this->notification();
        $service = app(PushDispatchService::class);

        $service->stageDeliveries($notification);
        for ($i = 0; $i < 3; $i++) {
            $service->dispatch($notification->fresh(), $this->stubWebPush(500));
        }

        $delivery = PushDelivery::where('notification_id', $notification->id)->firstOrFail();
        $this->assertSame(3, $delivery->attempts);
        $this->assertSame('failed', $notification->fresh()->status);

        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('admin.push.retry', $notification))
            ->assertRedirect();

        $this->assertSame(0, $delivery->fresh()->attempts);
        $this->assertSame('scheduled', $notification->fresh()->status);
        $this->assertTrue(AuditLog::where('event', 'push.notification.retry_queued')->exists());
    }

    /* ─────────────── consent preferences (§3.4/§3.6) ─────────────── */

    public function test_preferences_are_owner_only_and_claim_an_anonymous_subscription(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $endpoint = 'https://fcm.googleapis.com/fcm/send/pref-owner';
        $this->subscription(['endpoint' => $endpoint]); // subscribed before signing in

        $this->postJson(route('push.preferences'), [
            'endpoint' => $endpoint,
            'wants_editorial' => false,
            'wants_promotions' => true,
        ])->assertUnauthorized();

        $this->actingAs($owner)
            ->postJson(route('push.preferences'), [
                'endpoint' => $endpoint,
                'wants_editorial' => false,
                'wants_promotions' => true,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $record = PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->firstOrFail();
        $this->assertSame($owner->id, $record->user_id); // claimed on first use
        $this->assertFalse($record->wants_editorial);
        $this->assertTrue($record->wants_promotions);

        $this->actingAs($other)
            ->postJson(route('push.preferences.state'), ['endpoint' => $endpoint])
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson(route('push.preferences.state'), ['endpoint' => $endpoint])
            ->assertOk()
            ->assertJson(['wants_editorial' => false, 'wants_promotions' => true]);
    }

    /* ─────────────── in-site announcement centre (§3.4 fallback) ─────────────── */

    public function test_the_announcements_page_shows_only_public_sent_notifications(): void
    {
        $public = $this->notification([
            'title' => 'Public volume announcement',
            'status' => 'sent',
            'sent_at' => now()->subHour(),
        ]);
        $internal = $this->notification([
            'title' => 'Internal editors notice',
            'audience' => 'editors',
            'status' => 'sent',
            'sent_at' => now()->subHour(),
        ]);
        $expired = $this->notification([
            'title' => 'Expired announcement',
            'status' => 'sent',
            'sent_at' => now()->subDay(),
            'expires_at' => now()->subHour(),
        ]);
        $draft = $this->notification([
            'title' => 'Draft announcement',
            'status' => 'draft',
        ]);

        $response = $this->get(route('announcements'));

        $response->assertOk()
            ->assertSee($public->title)
            ->assertDontSee($internal->title)
            ->assertDontSee($expired->title)
            ->assertDontSee($draft->title);

        $this->assertSame(1, $response->viewData('announcements')->total());
    }

    /* ─────────────── composer, authorisation and reporting (§3.3) ─────────────── */

    public function test_notification_creation_endpoints_are_role_and_permission_gated(): void
    {
        foreach (['Author', 'Editor', 'Reviewer', 'Managing Editor'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('admin.push.index'))
                ->assertForbidden();
        }

        $this->actingAs($this->userWithRole('Admin'))
            ->get(route('admin.push.index'))
            ->assertOk();
    }

    public function test_admins_can_compose_a_notification_and_send_immediately(): void
    {
        $this->subscription();
        $admin = $this->userWithRole('Admin');

        // The composer's immediate-dispatch path is exercised with the
        // service's dispatch stubbed (real delivery success is proven in the
        // service tests above with a controlled endpoint stub); staging and
        // reporting stay untouched.
        $stub = $this->getMockBuilder(PushDispatchService::class)
            ->onlyMethods(['dispatch'])
            ->getMock();
        $stub->method('dispatch')->willReturnCallback(function (PushNotification $notification) {
            PushDelivery::where('notification_id', $notification->id)
                ->where('status', PushDelivery::PENDING)
                ->update(['status' => 'sent', 'delivered_at' => now()]);
            $notification->update(['status' => 'sent', 'sent_at' => now()]);

            return ['sent' => 1, 'failed' => 0, 'disabled' => 0, 'deliveries' => 1];
        });
        $this->instance(PushDispatchService::class, $stub);

        $this->actingAs($admin)
            ->post(route('admin.push.store'), [
                'title' => 'Volume 8 is out now',
                'message' => 'Read the latest volume of the journal today.',
                'category' => 'new_volume',
                'audience' => 'all',
                'submit_action' => 'send_now',
            ])
            ->assertRedirect();

        $notification = PushNotification::where('title', 'Volume 8 is out now')->firstOrFail();
        $this->assertSame('sent', $notification->status);
        $this->assertSame(1, $notification->deliveries()->where('status', 'sent')->count());
        $this->assertTrue(AuditLog::where('event', 'push.notification.created')->where('auditable_id', $notification->id)->exists());
    }

    public function test_invalid_destination_urls_are_refused(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('admin.push.store'), [
                'title' => 'Bad destination',
                'message' => 'Late-bound payload.',
                'category' => 'journal_notice',
                'audience' => 'all',
                'action_url' => 'javascript:alert(1)',
                'submit_action' => 'draft',
            ])
            ->assertSessionHasErrors('action_url');
    }

    public function test_message_length_limits_are_enforced(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('admin.push.store'), [
                'title' => str_repeat('t', 121),
                'message' => str_repeat('m', 301),
                'category' => 'new_issue',
                'audience' => 'all',
                'submit_action' => 'draft',
            ])
            ->assertSessionHasErrors(['title', 'message']);
    }

    /* ─────────────── campaign linkage (Module 2 → Module 3) ─────────────── */

    public function test_a_dispatched_notification_links_back_to_its_campaign(): void
    {
        $placement = \App\Models\AdPlacement::where('key', 'homepage_banner')->firstOrFail();

        $campaign = \App\Models\AdCampaign::create([
            'placement_id' => $placement->id,
            'created_by' => $this->userWithRole('Admin')->id,
            'title' => 'Sponsored issue promo',
            'type' => 'banner',
            'audience' => 'public',
            'label' => 'Sponsored',
            'status' => 'published',
        ]);

        $this->subscription(['wants_promotions' => true]);

        $stub = $this->getMockBuilder(PushDispatchService::class)
            ->onlyMethods(['dispatch'])
            ->getMock();
        $stub->method('dispatch')->willReturnCallback(function (PushNotification $notification) {
            PushDelivery::where('notification_id', $notification->id)
                ->where('status', PushDelivery::PENDING)
                ->update(['status' => 'sent', 'delivered_at' => now()]);
            $notification->update(['status' => 'sent', 'sent_at' => now()]);

            return ['sent' => 1, 'failed' => 0, 'disabled' => 0, 'deliveries' => 1];
        });
        $this->instance(PushDispatchService::class, $stub);

        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('admin.push.store'), [
                'title' => 'Sponsored announcement',
                'message' => 'A sponsored notice for subscribers who opted in.',
                'category' => 'sponsored_announcement',
                'audience' => 'promotions',
                'ad_campaign_id' => $campaign->id,
                'submit_action' => 'send_now',
            ])
            ->assertRedirect();

        $notification = PushNotification::where('title', 'Sponsored announcement')->firstOrFail();

        $this->assertSame($notification->id, $campaign->fresh()->push_campaign_id);
    }

    /* ─────────────── category eligibility (§3.2 administrator decision) ─────────────── */

    public function test_ineligible_categories_cannot_reach_the_public(): void
    {
        $this->subscription();
        PushCategorySetting::where('category', 'new_issue')->update(['is_eligible' => false]);

        $notification = $this->notification();
        $service = app(PushDispatchService::class);

        $this->assertSame(0, $service->stageDeliveries($notification));
        $this->assertSame(0, PushDelivery::count());
    }

    public function test_super_admin_alone_configures_category_eligibility(): void
    {
        $setting = PushCategorySetting::where('category', 'new_issue')->first();

        $this->actingAs($this->userWithRole('Admin'))
            ->get(route('admin.push.categories'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('admin.push.categories.update'), [
                'settings' => [['id' => $setting->id, 'is_eligible' => false]],
            ])
            ->assertForbidden();
        $this->assertTrue($setting->fresh()->is_eligible);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.push.categories.update'), [
                'settings' => [['id' => $setting->id, 'is_eligible' => false]],
            ])
            ->assertRedirect();

        $this->assertFalse($setting->fresh()->is_eligible);
        $this->assertTrue(AuditLog::where('event', 'push.categories.updated')->exists());
    }
}
