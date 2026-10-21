<?php

namespace Tests\Feature\Addendum;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use App\Models\AdPlacement;
use App\Models\User;
use App\Services\Permissions\PermissionRegistrar;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Addendum Module 2 — Advertisements & Announcements.
 *
 * Everything is server-authorised: no screen button is trusted. Campaign
 * records are never deleted, only archived; expired campaigns stop appearing
 * by window logic alone; every public impression/click is counted once per
 * visitor fingerprint per day.
 */
class Module2AdvertisingTest extends TestCase
{
    use RefreshDatabase;

    /** A real 1×1 PNG; media validation checks content, and GD is not needed. */
    private const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        app(EditorialWorkflowService::class)->seed();

        // Creates any missing catalogue permissions (idempotent) and grants
        // them per the role matrix — this carries the new advertising
        // permissions without touching anything else.
        app(PermissionRegistrar::class)->sync();

        Storage::fake('public');
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

    private function superAdmin(): User
    {
        return $this->userWithRole('Super Admin');
    }

    private function makeCampaign(string $placementKey, array $attributes = []): AdCampaign
    {
        $placement = AdPlacement::where('key', $placementKey)->firstOrFail();

        return AdCampaign::create(array_merge([
            'placement_id' => $placement->id,
            'created_by' => $this->userWithRole('Admin')->id,
            'title' => 'A Recorded Campaign '.strtolower(\Illuminate\Support\Str::random(4)),
            'type' => 'image',
            'audience' => 'public',
            'label' => 'Advertisement',
            'status' => 'published',
            'destination_url' => 'https://example.org/promo',
            'priority' => 5,
        ], $attributes));
    }

    /* ─────────────── authorisation ─────────────── */

    public function test_guests_cannot_open_the_advertisement_manager(): void
    {
        $this->get(route('admin.advertisements.index'))->assertRedirect(route('login'));
        $this->post(route('admin.advertisements.store', ['title' => 'x']))->assertRedirect(route('login'));
    }

    public function test_authors_editors_and_managing_editors_cannot_administer_advertisements(): void
    {
        foreach (['Author', 'Editor', 'Managing Editor'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('admin.advertisements.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_may_open_the_campaign_dashboard_and_super_admin_the_placements(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.advertisements.index'))
            ->assertOk()
            ->assertSee('Campaigns');

        // Admin is associative in the catalogue and deliberately does not hold
        // the system-wide placement configuration power.
        $this->actingAs($this->admin())
            ->get(route('admin.advertisements.placements'))
            ->assertForbidden();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.advertisements.placements'))
            ->assertOk()
            ->assertSee('Placement configuration');
    }

    public function test_admin_without_advertising_permission_is_prevented_from_the_config_gate_even_if_role_touched(): void
    {
        // The catalogue does not grant advertising.configure to Admin — the
        // server-side gate stays closed even if the interface were circumvented.
        $response = $this->actingAs($this->admin())
            ->post(route('admin.advertisements.placements.update'), [
                'placement_id' => AdPlacement::where('key', 'homepage_banner')->first()->id,
                'label' => 'Hacked label',
                'is_active' => false,
            ]);

        $response->assertForbidden();
        $this->assertSame('Homepage banner', AdPlacement::where('key', 'homepage_banner')->first()->label);
        $this->assertTrue(AdPlacement::where('key', 'homepage_banner')->first()->is_active);
    }

    /* ─────────────── placement configuration (Super Admin) ─────────────── */

    public function test_super_admin_can_deactivate_a_placement_and_its_slot_goes_publicly_silent(): void
    {
        $this->makeCampaign('homepage_banner', ['title' => 'Visible On The Homepage']);

        $this->get('/')->assertOk()->assertSee('Visible On The Homepage');

        $placement = AdPlacement::where('key', 'homepage_banner')->first();
        $this->actingAs($this->superAdmin())
            ->post(route('admin.advertisements.placements.update'), [
                'placement_id' => $placement->id,
                'label' => $placement->label,
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->get('/')->assertOk()->assertDontSee('Visible On The Homepage');
    }

    /* ─────────────── creation, media and validation ─────────────── */

    public function test_admin_can_create_an_image_campaign_and_the_record_is_served(): void
    {
        $placement = AdPlacement::where('key', 'homepage_banner')->first();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.advertisements.store'), [
                'title' => 'University Fair 2026',
                'description' => 'A genuine advertisement record.',
                'type' => 'image',
                'placement_id' => $placement->id,
                'audience' => 'public',
                'label' => 'Advertisement',
                'destination_url' => 'https://example.org/fair',
                'priority' => 3,
                'submit_status' => 'publish', // via the "Save & approve" button
                'media' => \Illuminate\Http\UploadedFile::fake()->createWithContent(
                    'fair.png',
                    base64_decode(self::TINY_PNG)
                ),
            ]);

        // The manager redirects to the campaign's own page after creation.
        $response->assertRedirect();

        $campaign = AdCampaign::where('title', 'University Fair 2026')->firstOrFail();
        $this->assertSame('published', $campaign->status);
        $this->assertSame('image', $campaign->media_type);
        $this->assertNotNull($campaign->media_path);
        Storage::disk('public')->assertExists($campaign->media_path);

        $this->get('/')->assertOk()->assertSee('University Fair 2026');
    }

    public function test_javascript_and_other_non_web_schemes_are_refused_for_urls(): void    {
        $placement = AdPlacement::where('key', 'homepage_banner')->first();

        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.store'), [
                'title' => 'Malicious Campaign',
                'type' => 'image',
                'placement_id' => $placement->id,
                'audience' => 'public',
                'label' => 'Advertisement',
                'destination_url' => 'javascript:alert(1)',
            ])
            ->assertSessionHasErrors('destination_url');

        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.store'), [
                'title' => 'Malicious Campaign',
                'type' => 'image',
                'placement_id' => $placement->id,
                'audience' => 'public',
                'label' => 'Advertisement',
                'external_media_url' => 'data:text/html,evil',
            ])
            ->assertSessionHasErrors('external_media_url');

        $this->assertNull(AdCampaign::where('title', 'Malicious Campaign')->first());
    }

    public function test_a_pdf_cannot_pass_as_image_media(): void
    {
        $placement = AdPlacement::where('key', 'homepage_banner')->first();

        // A select the way a real browser reports a PDF: client declares
        // `application/pdf` and the uploaded bytes are a PDF.
        $temp = tempnam(sys_get_temp_dir(), 'pdfsim');
        file_put_contents($temp, "%PDF-1.4 definitely a pdf\n%%EOF");
        $pdf = new \Illuminate\Http\UploadedFile($temp, 'evil.pdf', 'application/pdf', 0, true);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.advertisements.store'), [
                'title' => 'Not Actually An Image',
                'type' => 'image',
                'placement_id' => $placement->id,
                'audience' => 'public',
                'label' => 'Advertisement',
                'media' => $pdf,
            ]);

        $response->assertSessionHasErrors('media');

        @unlink($temp);

        $this->assertNull(AdCampaign::where('title', 'Not Actually An Image')->first());
    }

    /* ─────────────── serving windows ─────────────── */

    public function test_draft_campaigns_are_invisible_on_public_pages(): void
    {
        $this->makeCampaign('homepage_banner', [
            'title' => 'A Draft Campaign',
            'status' => 'draft',
        ]);

        $this->get('/')->assertOk()->assertDontSee('A Draft Campaign');
    }

    public function test_expired_campaigns_stop_appearing_without_any_manual_step(): void
    {
        $this->makeCampaign('homepage_banner', [
            'title' => 'Long Gone Campaign',
            'ends_at' => now()->subDay(),
            'status' => 'published', // window decides, not the state column
        ]);

        $this->get('/')->assertOk()->assertDontSee('Long Gone Campaign');
    }

    public function test_scheduled_campaigns_appear_the_moment_their_window_opens(): void
    {
        $this->makeCampaign('homepage_banner', [
            'title' => 'Not Yet Campaign',
            'starts_at' => now()->addDay(),
        ]);

        $this->get('/')->assertOk()->assertDontSee('Not Yet Campaign');

        Carbon::setTestNow(now()->addDays(2));

        $this->get('/')->assertOk()->assertSee('Not Yet Campaign');

        Carbon::setTestNow();
    }

    public function test_published_campaigns_render_on_their_placements_and_announcements_run_sitewide(): void
    {
        $this->makeCampaign('homepage_banner', ['title' => 'Homepage Wide Ad']);
        $this->makeCampaign('announcement_strip', ['title' => 'Volume 8 Announcement', 'label' => 'Announcement']);

        $home = $this->get('/');
        $home->assertOk()
            ->assertSee('Homepage Wide Ad')
            ->assertSee('Advertisement')
            ->assertSee('Volume 8 Announcement')
            ->assertSee('Announcement');
    }

    /* ─────────────── engagement: honest, deduplicated counting ─────────────── */

    public function test_clicks_redirect_to_the_recorded_destination_and_are_counted_once_per_fingerprint_per_day(): void
    {
        $campaign = $this->makeCampaign('homepage_banner', ['title' => 'Clickable Campaign']);

        $this->get(route('ads.click', $campaign))
            ->assertRedirect('https://example.org/promo');

        $this->get(route('ads.click', $campaign))
            ->assertRedirect('https://example.org/promo');

        $this->assertSame(1, AdEvent::where('campaign_id', $campaign->id)->where('event', 'click')->count());
    }

    public function test_impressions_are_counted_once_per_visitor_per_day_not_once_per_render(): void
    {
        $campaign = $this->makeCampaign('homepage_banner', ['title' => 'Impression Counted Ad']);

        $this->get('/');
        $this->get('/');
        $this->get('/');

        $this->assertSame(1, AdEvent::where('campaign_id', $campaign->id)->where('event', 'impression')->count());
    }

    public function test_campaigns_without_a_destination_are_not_clickable_and_click_redirect_is_honest(): void
    {
        $campaign = $this->makeCampaign('homepage_banner', [
            'title' => 'Announcement Only',
            'destination_url' => null,
        ]);

        $this->get(route('ads.click', $campaign))->assertNotFound();
        $this->assertSame(0, AdEvent::where('campaign_id', $campaign->id)->count());
    }

    /* ─────────────── lifecycle: transitions and archival ─────────────── */

    public function test_status_transitions_are_validated_server_side(): void
    {
        $campaign = $this->makeCampaign('homepage_banner', ['title' => 'State Machine Campaign', 'status' => 'draft']);
        $admin = $this->admin();

        // draft → published is allowed directly.
        $this->actingAs($admin)
            ->post(route('admin.advertisements.transition', $campaign), ['status' => 'published'])
            ->assertRedirect();
        $this->assertSame('published', $campaign->fresh()->status);

        // published → draft is not a legal transition.
        $this->actingAs($admin)
            ->post(route('admin.advertisements.transition', $campaign), ['status' => 'draft'])
            ->assertStatus(422);
        $this->assertSame('published', $campaign->fresh()->status);

        // published → paused → published (pause/resume).
        $this->actingAs($admin)
            ->post(route('admin.advertisements.transition', $campaign), ['status' => 'paused'])
            ->assertRedirect();
        $this->get('/')->assertOk()->assertDontSee('State Machine Campaign');

        $this->actingAs($admin)
            ->post(route('admin.advertisements.transition', $campaign), ['status' => 'published'])
            ->assertRedirect();
        $this->get('/')->assertOk()->assertSee('State Machine Campaign');
    }

    public function test_campaigns_are_archived_never_deleted(): void
    {
        $campaign = $this->makeCampaign('homepage_banner', ['title' => 'Retired Campaign']);
        $id = $campaign->id;

        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.transition', $campaign), ['status' => 'archived'])
            ->assertRedirect();

        $this->assertSame('archived', AdCampaign::find($id)->status);
    }

    /* ─────────────── public surfaces ─────────────── */

    public function test_calls_for_papers_page_lists_visible_calls_and_hides_everything_else(): void
    {
        $this->makeCampaign('calls_for_papers', [
            'title' => 'Call for Papers: Special Edition',
            'type' => 'call_for_papers',
            'label' => 'Announcement',
        ]);
        $this->makeCampaign('calls_for_papers', [
            'title' => 'Stale Call',
            'type' => 'call_for_papers',
            'ends_at' => now()->subDay(),
        ]);
        $this->makeCampaign('sponsored_section', [
            'title' => 'Sponsored Research Equipment',
            'type' => 'sponsored',
            'label' => 'Sponsored',
        ]);
        $this->makeCampaign('homepage_banner', [
            'title' => 'Not On This Page',
        ]);

        $page = $this->get(route('ads.calls'));
        $page->assertOk()
            ->assertSee('Call for Papers: Special Edition')
            ->assertSee('Sponsored Research Equipment')
            ->assertSee('Sponsored')
            ->assertDontSee('Stale Call')
            ->assertDontSee('Not On This Page');
    }

    public function test_issue_and_article_pages_render_their_slots(): void
    {
        $issue = \App\Models\Issue::create([
            'volume_id' => \App\Models\Volume::create([
                'title' => 'Ad Slot Volume',
                'slug' => 'ad-slot-volume-'.strtolower(\Illuminate\Support\Str::random(4)),
                'volume_no' => 9,
                'year' => '2026',
                'status' => 'published',
            ])->id,
            'title' => 'Ad Slot Issue',
            'slug' => 'ad-slot-issue-'.strtolower(\Illuminate\Support\Str::random(4)),
            'issue_no' => 2,
            'status' => 'published',
            'published_on' => '2026-03-01',
        ]);

        $author = User::factory()->create();
        $submission = \App\Models\Submission::factory()->create([
            'author_id' => $author->id,
            'status' => 'published',
        ]);
        $article = \App\Models\Article::create([
            'submission_id' => $submission->id,
            'author_id' => $author->id,
            'issue_id' => $issue->id,
            'title' => 'An Article With A Banner',
            'slug' => 'an-article-with-a-banner',
            'abstract' => 'Abstract.',
            'status' => 'published',
            'published_on' => '2026-03-01',
        ]);

        $this->makeCampaign('issue_promo', ['title' => 'Issue Page Promo']);
        $this->makeCampaign('article_banner', ['title' => 'Article Page Banner']);

        $this->get(route('issue.show', $issue->id))
            ->assertOk()
            ->assertSee('Issue Page Promo');

        $this->get(route('article.show', $article->slug))
            ->assertOk()
            ->assertSee('Article Page Banner');
    }

    public function test_campaign_performance_reporting_is_visible_to_admins(): void
    {
        $campaign = $this->makeCampaign('homepage_banner', ['title' => 'Reported Campaign']);

        $this->get('/');
        $this->get(route('ads.click', $campaign));

        $this->actingAs($this->admin())
            ->get(route('admin.advertisements.show', $campaign))
            ->assertOk()
            ->assertSee('Performance')
            ->assertSee('CTR');
    }
}
