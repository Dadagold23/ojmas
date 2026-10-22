<?php

namespace Tests\Feature\Addendum;

use App\Models\AdCampaign;
use App\Models\AdPlacement;
use App\Models\AuditLog;
use App\Models\Article;
use App\Models\ContactMessage;
use App\Models\Issue;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Models\Volume;
use App\Models\User;
use App\Services\Permissions\PermissionRegistrar;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Addendum Module 6 — integration, security and QA consolidation.
 *
 * This suite verifies the §6.1 security requirements end to end as they are
 * now implemented: server-side authorisation on every addendum surface,
 * access control on restricted files, rate limiting on public endpoints,
 * encryption-at-rest for subscription secrets, and audit trails for campaign
 * and notification actions discovered by the consolidation audit.
 *
 * The §6.2 QA checklist (archive, citations, multi-author bylines, PDF
 * access, campaign scheduling/expiry, subscription round trips, delivery
 * outcomes, analytics) is covered by the module suites; the deliverables
 * document maps every checklist item to its exact tests.
 */
class Module6SecurityIntegrationTest extends TestCase
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
    }

    /* ─────────────── fixtures ─────────────── */

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function campaignPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'QA Security Campaign '.strtolower(\Illuminate\Support\Str::random(4)),
            'type' => 'image',
            'placement_id' => AdPlacement::where('key', 'homepage_banner')->value('id'),
            'audience' => 'public',
            'label' => 'Advertisement',
            'submit_status' => 'draft',
        ], $overrides);
    }

    /* ─────────────── §6.1 authorization ─────────────── */

    public function test_only_authorized_personnel_may_publish_advertisements_or_send_notifications(): void
    {
        // Neither the store endpoints nor a crafted status/send request may
        // be driven by non-administration roles — the authorisation is
        // checked on the server for every request, not just the interface.
        $payload = $this->campaignPayload();
        foreach (['Author', 'Editor', 'Reviewer', 'Managing Editor'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->post(route('admin.advertisements.store'), $payload)
                ->assertForbidden();

            $this->actingAs($this->userWithRole($role))
                ->post(route('admin.push.store'), [
                    'title' => 'Unauthorised send attempt',
                    'message' => 'Attempted from a non-administration role.',
                    'category' => 'new_issue',
                    'audience' => 'all',
                    'submit_action' => 'send_now',
                ])
                ->assertForbidden();
        }

        $this->assertSame(0, PushNotification::count());
        $this->assertSame(0, AdCampaign::count());

        // Administration holds both keys; nothing else counts.
        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('admin.advertisements.store'), $payload)
            ->assertRedirect(route('admin.advertisements.show', AdCampaign::first()));
        $this->assertSame(1, AdCampaign::count());
    }

    /* ─────────────── §6.1 audit trails ─────────────── */

    public function test_campaign_changes_and_status_changes_are_audited(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->post(route('admin.advertisements.store'), $this->campaignPayload())
            ->assertRedirect();
        $campaign = AdCampaign::first();

        $created = AuditLog::where('event', 'advertising.campaign.created')
            ->where('auditable_id', $campaign->id)->first();
        $this->assertNotNull($created);
        $this->assertSame($admin->id, $created->user_id);
        $this->assertSame('draft', $created->new_values['status']);

        $this->actingAs($admin)
            ->post(route('admin.advertisements.edit', $campaign), $this->campaignPayload([
                'title' => 'QA Security Campaign (renamed)',
            ]))
            ->assertRedirect();

        $updated = AuditLog::where('event', 'advertising.campaign.updated')
            ->where('auditable_id', $campaign->id)->first();
        $this->assertNotNull($updated);
        $this->assertSame('QA Security Campaign (renamed)', $updated->new_values['after']['title']);
        $this->assertNotSame($updated->new_values['before']['title'], $updated->new_values['after']['title']);

        $this->actingAs($admin)
            ->post(route('admin.advertisements.transition', $campaign), ['status' => 'published'])
            ->assertRedirect();

        $transition = AuditLog::where('event', 'advertising.campaign.status_changed')
            ->where('auditable_id', $campaign->id)->first();
        $this->assertNotNull($transition);
        $this->assertSame('draft', $transition->new_values['from']);
        $this->assertSame('published', $transition->new_values['to']);
    }

    public function test_placement_changes_are_audited(): void
    {
        $placement = AdPlacement::firstOrFail();

        $this->actingAs($this->userWithRole('Super Admin'))
            ->post(route('admin.advertisements.placements.update'), [
                'placement_id' => $placement->id,
                'label' => 'Renamed slot',
                'is_active' => false,
            ])
            ->assertRedirect();

        $audit = AuditLog::where('event', 'advertising.placement.updated')
            ->where('auditable_type', AdPlacement::class)
            ->where('auditable_id', $placement->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertFalse($audit->new_values['after']['is_active']);
        $this->assertSame('Renamed slot', $audit->new_values['after']['label']);
    }

    public function test_notification_cancellations_are_audited(): void
    {
        $admin = $this->userWithRole('Admin');
        $notification = PushNotification::create([
            'created_by' => $admin->id,
            'title' => 'A queued notice',
            'message' => 'Queued for the retention-and-audit check.',
            'category' => 'journal_notice',
            'audience' => 'all',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.push.cancel', $notification), ['reason' => 'Development testing only'])
            ->assertRedirect();

        $audit = AuditLog::where('event', 'push.notification.cancelled')
            ->where('auditable_id', $notification->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('Development testing only', $audit->new_values['reason']);
        $this->assertSame('cancelled', $notification->fresh()->status);
    }

    /* ─────────────── §6.1 restricted media and files ─────────────── */

    public function test_an_unpublished_issue_pdf_is_not_publicly_downloadable(): void
    {
        $volume = Volume::create(['title' => 'Volume 7', 'year' => 2026, 'volume_no' => 7, 'status' => 'draft', 'slug' => 'qa-vol-7']);
        $draft = Issue::create(['volume_id' => $volume->id, 'issue_no' => '1', 'title' => 'Draft issue', 'status' => 'draft', 'slug' => 'qa-draft-issue']);

        $published = Issue::create(['volume_id' => $volume->id, 'issue_no' => '2', 'title' => 'Published issue', 'status' => 'published', 'published_on' => '2026-09-01', 'slug' => 'qa-published-issue']);
        Storage::fake('public');
        Storage::disk('public')->put('issues/compiled.pdf', '%PDF-1.4 qa');
        $published->update(['pdf_path' => 'issues/compiled.pdf']);

        // The draft issue is editorial material: neither its page nor its PDF.
        $this->get(route('issue.show', $draft->id))->assertNotFound();
        $this->get(route('issue.pdf', $draft->id))->assertNotFound();

        // The published issue still hands out its compiled journal.
        $response = $this->get(route('issue.pdf', $published->id));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    public function test_unpublished_articles_do_not_leak_through_public_pages(): void
    {
        $volume = Volume::create(['title' => 'Volume 7', 'year' => 2026, 'volume_no' => 7, 'status' => 'published', 'slug' => 'qa-vol-7b']);
        $issue = Issue::create(['volume_id' => $volume->id, 'issue_no' => '1', 'title' => 'Open issue', 'status' => 'published', 'published_on' => '2026-09-01', 'slug' => 'qa-open-issue']);
        $submission = \App\Models\Submission::factory()->create();
        $article = Article::create([
            'submission_id' => $submission->id,
            'author_id' => $submission->author_id ?? $this->userWithRole('Author')->id,
            'issue_id' => $issue->id,
            'title' => 'A still-private article',
            'slug' => 'a-still-private-article',
            'abstract' => 'An abstract that has not been published yet.',
            'full_text' => 'Body text that no reader should reach through public pages.',
            'status' => 'draft',
        ]);

        $this->get(route('issue.show', $issue->id))->assertOk()->assertDontSee('A still-private article');
        $this->get(route('article.show', $article->slug))->assertNotFound();
        $this->get(route('article.pdf', $article->slug))->assertNotFound();
        $this->get(route('article.cite', [$article->slug, 'apa']))->assertNotFound();
    }

    /* ─────────────── §6.1 subscription secret handling ─────────────── */

    public function test_subscription_endpoints_and_keys_are_encrypted_and_never_serialised(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/qa-secret-1';
        $subscription = PushSubscription::create([
            'user_id' => null,
            'endpoint_hash' => hash('sha256', $endpoint),
            'endpoint' => $endpoint,
            'p256dh' => 'BeWq0P4GJX0fnN8name0P4GJX0fnN8nameN8name=',
            'auth' => 'aW1hZ2luZVZhbGlkQXV0aA==',
            'wants_editorial' => true,
            'wants_promotions' => false,
            'is_active' => true,
        ]);

        $fresh = $subscription->fresh();

        // At rest, none of the secrets is stored in the clear.
        foreach (['endpoint', 'p256dh', 'auth'] as $secret) {
            $raw = (string) $fresh->getRawOriginal($secret);
            $this->assertStringNotContainsString('fcm.googleapis.com', $raw);
            $this->assertStringNotContainsString('nameN8name', $raw);
            $this->assertStringNotContainsString('aW1hZ2luZVZhbGlk', $raw);
            $this->assertNotSame($endpoint, $raw);
        }

        // And they never travel in serialisations.
        $serialised = $fresh->toArray();
        $this->assertArrayNotHasKey('endpoint', $serialised);
        $this->assertArrayNotHasKey('p256dh', $serialised);
        $this->assertArrayNotHasKey('auth', $serialised);

        // Yet the delivery path reads the plaintext exactly as before.
        $this->assertSame($endpoint, $fresh->endpoint);
        $this->assertTrue($fresh->isDeliverable());
    }

    /* ─────────────── §6.1 rate limiting on public endpoints ─────────────── */

    public function test_the_public_contact_form_is_rate_limited(): void
    {
        $message = fn (int $n) => [
            'full_name' => 'QA Throttle Key',
            'email' => 'throttle'.$n.'@example.org',
            'subject' => 'Rate limit probe '.$n,
            'message' => 'A contact attempt within the per-minute limit.',
        ];

        for ($i = 1; $i <= 10; $i++) {
            $this->post(route('contact.store'), $message($i))->assertRedirect();
        }
        $this->assertSame(10, ContactMessage::count());

        $this->post(route('contact.store'), $message(11))->assertStatus(429);
        $this->assertSame(10, ContactMessage::count(), 'The throttled request must not be recorded.');
    }

    /* ─────────────── §6.2 QA integration spot-checks ─────────────── */

    public function test_public_issues_still_render_the_announcement_strip_and_ad_click_redirects(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('admin.advertisements.store'), $this->campaignPayload([
                'submit_status' => 'publish', // approved: live within the window
                'destination_url' => 'https://userjourney.example.org/campaign',
            ]))
            ->assertRedirect();
        $campaign = AdCampaign::first();

        $this->get('/')->assertOk()->assertSee($campaign->title);

        // Destination links resolve through the recorded click route only —
        // a 302 to the recorded destination (recording the click first).
        $this->get(route('ads.click', $campaign))->assertRedirect();
    }

    public function test_the_full_public_notification_round_trip_still_works_after_hardening(): void
    {
        // Subscribe → record → unsubscribe, using the throttled public
        // endpoints, with the app key unchanged by the encryption rollout.
        $endpoint = 'https://fcm.googleapis.com/fcm/send/qa-roundtrip';

        $this->post(route('push.subscribe'), [
            'endpoint' => $endpoint,
            'p256dh' => 'BeWq0P4GJX0fnN8name0P4GJX0fnN8nameN8name=',
            'auth' => 'aW1hZ2luZVZhbGlkQXV0aA==',
            'wants_editorial' => true,
        ])->assertOk()->assertJson(['ok' => true]);

        $subscription = PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->first();
        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->is_active);
        $this->assertSame($endpoint, $subscription->endpoint);

        $this->post(route('push.unsubscribe'), ['endpoint' => $endpoint])->assertOk();
        $this->assertFalse($subscription->fresh()->is_active); // disabled, never deleted
    }

    public function test_the_pre_encryption_migration_command_encrypts_clear_text_secrets(): void
    {
        $id = DB::table('push_subscriptions')->insertGetId([
            'user_id' => null,
            'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/fcm/send/legacy-1'),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/legacy-1',
            'p256dh' => 'BeWq0P4GJX0fnN8name0P4GJX0fnN8nameN8name=',
            'auth' => 'aW1hZ2luZVZhbGlkQXV0aA==',
            'wants_editorial' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('push:reencrypt-secrets')->assertSuccessful();

        foreach (['endpoint', 'p256dh', 'auth'] as $column) {
            $raw = (string) DB::table('push_subscriptions')->find($id)->{$column};
            $this->assertStringStartsWith('eyJ', $raw);
            $this->assertStringNotContainsString('fcm.googleapis.com', $raw);
        }

        // The model reads the migrated row exactly as the plaintext it was.
        $model = PushSubscription::find($id);
        $this->assertSame('https://fcm.googleapis.com/fcm/send/legacy-1', $model->endpoint);
        $this->assertTrue($model->isDeliverable());
    }
}
