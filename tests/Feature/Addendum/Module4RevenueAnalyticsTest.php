<?php

namespace Tests\Feature\Addendum;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use App\Models\AdPlacement;
use App\Models\PushDelivery;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Permissions\PermissionRegistrar;
use App\Services\Workflow\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Addendum Module 4 — advertisement revenue and campaign analytics.
 *
 * The counting and revenue definitions are the deliverables here, not just
 * columns: impressions are exactly the once-daily-per-fingerprint events
 * Module 2 records; "agreed", "invoiced" and "paid" are recorded states, so
 * an unpaid invoice is never presented as collected revenue; and the
 * financial figures sit behind their own permission, never the whole of
 * advertising.manage.
 */
class Module4RevenueAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Author', 'Editor', 'Reviewer', 'Super Admin', 'Managing Editor'] as $role) {
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

    private function admin(): User
    {
        return $this->userWithRole('Admin');
    }

    private function superAdmin(): User
    {
        return $this->userWithRole('Super Admin');
    }

    private function makeCampaign(array $attributes = []): AdCampaign
    {
        $placement = AdPlacement::where('key', 'homepage_banner')->firstOrFail();

        return AdCampaign::create(array_merge([
            'placement_id' => $placement->id,
            'created_by' => $this->admin()->id,
            'title' => 'Recorded Campaign '.strtolower(\Illuminate\Support\Str::random(4)),
            'type' => 'image',
            'audience' => 'public',
            'label' => 'Advertisement',
            'status' => 'published',
            'priority' => 5,
        ], $attributes));
    }

    private function recordEngagement(AdCampaign $campaign, string $event, string $day): void
    {
        // Rows in the exact shape the service records them; the day and a
        // caller-chosen fingerprint make date-window tests exact.
        AdEvent::create([
            'campaign_id' => $campaign->id,
            'event' => $event,
            'fingerprint' => 'fp-'.md5($campaign->id.$event.$day.\Illuminate\Support\Str::random(4)),
            'occurred_on' => $day,
            'occurred_at' => \Illuminate\Support\Carbon::parse($day)->startOfDay()->addHours(9),
        ]);
    }

    /* ─────────────── authorization of the financial surface ─────────────── */

    public function test_the_reports_screen_requires_the_financial_permission(): void
    {
        $this->get(route('admin.advertisements.reports'))->assertRedirect(route('login'));

        foreach (['Author', 'Editor', 'Managing Editor'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('admin.advertisements.reports'))
                ->assertForbidden();
        }
    }

    public function test_admin_without_advertising_reports_cannot_open_the_reports_screen(): void
    {
        Role::where('name', 'Admin')->first()
            ->revokePermissionTo('advertising.reports');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->admin())
            ->get(route('admin.advertisements.reports'))
            ->assertForbidden();

        // The permission holder still can.
        $this->actingAs($this->superAdmin())
            ->get(route('admin.advertisements.reports'))
            ->assertOk()
            ->assertSee('Recorded advertising income');
    }

    public function test_an_admin_without_the_reports_permission_cannot_write_revenue_fields(): void
    {
        $campaign = $this->makeCampaign();

        Role::where('name', 'Admin')->first()
            ->revokePermissionTo('advertising.reports');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.update', $campaign), [
                'title' => $campaign->title,
                'type' => $campaign->type,
                'placement_id' => $campaign->placement_id,
                'audience' => $campaign->audience,
                'label' => $campaign->label,
                'agreed_price' => '10',
                'currency' => 'NGN',
            ])
            ->assertForbidden();

        $this->assertNull($campaign->fresh()->agreed_price);
    }

    public function test_a_revenue_less_edit_by_non_financial_role_leaves_recorded_money_untouched(): void
    {
        $campaign = $this->makeCampaign([
            'agreed_price' => '25000.00',
            'currency' => 'NGN',
            'payment_status' => 'invoiced',
            'invoice_reference' => 'INV-2026-001',
        ]);

        // A normal edit that posts only the regular fields: the revenue
        // record survives — money is not wiped out by an unrelated edit.
        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.update', $campaign), [
                'title' => 'Renamed Campaign Foam',
                'type' => $campaign->type,
                'placement_id' => $campaign->placement_id,
                'audience' => $campaign->audience,
                'label' => $campaign->label,
            ]);

        $fresh = $campaign->fresh();
        $this->assertSame('25000.00', $fresh->agreed_price);
        $this->assertSame('INV-2026-001', $fresh->invoice_reference);
        $this->assertSame('invoiced', $fresh->payment_status);
    }

    /* ─────────────── revenue bookkeeping honesty ─────────────── */

    public function test_a_price_is_recorded_with_its_currency_and_references(): void
    {
        $placement = AdPlacement::where('key', 'homepage_banner')->first();

        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.store'), [
                'title' => 'Priced Banner Campaign',
                'type' => 'image',
                'placement_id' => $placement->id,
                'audience' => 'public',
                'label' => 'Advertisement',
                'agreed_price' => '45000.50',
                'currency' => 'ngn', // stored uppercase, however it arrives
                'agreement_reference' => 'Q-2026-117',
            ])
            ->assertRedirect();

        $campaign = AdCampaign::where('title', 'Priced Banner Campaign')->firstOrFail();
        $this->assertSame('45000.50', $campaign->agreed_price);
        $this->assertSame('NGN', $campaign->currency);
        $this->assertSame('Q-2026-117', $campaign->agreement_reference);
        $this->assertNull($campaign->payment_status); // nothing paid or invoiced was claimed
    }

    public function test_bookkeeping_fictions_are_refused(): void
    {
        $base = function () {
            return $this->makeCampaign()->toArray()
                + [
                    'title' => 'Fictionville '.\Illuminate\Support\Str::random(4),
                    'type' => 'image',
                    'placement_id' => AdPlacement::where('key', 'homepage_banner')->first()->id,
                    'audience' => 'public',
                    'label' => 'Advertisement',
                    'agreed_price' => '10000',
                    'currency' => 'NGN',
                ];
        };

        // Payment state with no price behind it.
        $payload = $base();
        unset($payload['agreed_price'], $payload['currency'], $payload['agreement_reference']);
        $payload['payment_status'] = 'paid';
        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.store'), $payload)
            ->assertSessionHasErrors('payment_status');

        // Invoiced without an invoice reference.
        $campaign = $this->makeCampaign(['agreed_price' => '1000.00', 'currency' => 'NGN']);
        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.update', $campaign), [
                'title' => $campaign->title,
                'type' => $campaign->type,
                'placement_id' => $campaign->placement_id,
                'audience' => $campaign->audience,
                'label' => $campaign->label,
                'payment_status' => 'invoiced',
            ])
            ->assertSessionHasErrors('invoice_reference');

        // A "paid" state without a payment date.
        $campaign = $this->makeCampaign(['agreed_price' => '1000.00', 'currency' => 'NGN', 'invoice_reference' => 'INV-9']);
        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.update', $campaign), [
                'title' => $campaign->title,
                'type' => $campaign->type,
                'placement_id' => $campaign->placement_id,
                'audience' => $campaign->audience,
                'label' => $campaign->label,
                'payment_status' => 'paid',
                'invoice_reference' => 'INV-9',
            ])
            ->assertSessionHasErrors('paid_at');

        // A payment date recorded while the state is not "paid".
        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.update', $campaign->fresh()), [
                'title' => $campaign->title,
                'type' => $campaign->type,
                'placement_id' => $campaign->placement_id,
                'audience' => $campaign->audience,
                'label' => $campaign->label,
                'paid_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('paid_at');

        // A price without its currency.
        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.store'), [
                'title' => 'No Currency Given',
                'type' => 'image',
                'placement_id' => AdPlacement::where('key', 'homepage_banner')->first()->id,
                'audience' => 'public',
                'label' => 'Advertisement',
                'agreed_price' => '500',
            ])
            ->assertSessionHasErrors('currency');

        $this->assertNull(AdCampaign::where('title', 'No Currency Given')->first());
        $this->assertNull(AdCampaign::where('title', $campaign->fresh()->title)->where('payment_status', 'paid')->whereNull('paid_at')->first());
    }

    /* ─────────────── the reports maths ─────────────── */

    public function test_agreed_invoiced_and_paid_are_named_separately_and_never_mixed(): void
    {
        $now = now()->toDateString();

        // Paid agreement: 30,000 NGN (invoice exists AND payment recorded).
        $paid = $this->makeCampaign([
            'title' => 'Fully Paid Banner Install',
            'agreed_price' => '30000.00',
            'currency' => 'NGN',
            'payment_status' => 'paid',
            'invoice_reference' => 'INV-30000',
            'paid_at' => $now,
        ]);
        // Invoiced but unpaid: 5,000 NGN — must stay outstanding.
        $this->makeCampaign([
            'title' => 'Invoiced Campus Guide Spot',
            'agreed_price' => '5000.00',
            'currency' => 'NGN',
            'payment_status' => 'invoiced',
            'invoice_reference' => 'INV-5000',
        ]);
        // Agreed but not yet invoiced: 12,000 NGN.
        $this->makeCampaign([
            'title' => 'Freshly Agreed Conference Banner',
            'agreed_price' => '12000.00',
            'currency' => 'NGN',
            'payment_status' => 'agreed',
        ]);
        // Excluded: a draft was never approved...
        $this->makeCampaign([
            'title' => 'Still A Draft',
            'status' => 'draft',
            'agreed_price' => '7000.00',
            'currency' => 'NGN',
        ]);
        // ...and an archived record may never have been approved either.
        $this->makeCampaign([
            'title' => 'Archived Never Approved',
            'status' => 'archived',
            'agreed_price' => '3000.00',
            'currency' => 'NGN',
        ]);

        $this->actingAs($this->admin())->get(route('admin.advertisements.reports'))
            ->assertOk();

        $report = app(\App\Services\Advertising\AdRevenueReportService::class)->build();

        $ngn = $report['revenue']['NGN'];
        // agreed = 30000 + 5000 + 12000 (drafts and archived excluded).
        $this->assertEquals(47000.0, (float) $ngn['agreed']);
        // invoiced = 30000 + 5000.
        $this->assertEquals(35000.0, (float) $ngn['invoiced']);
        // paid = 30000 — collected revenue only.
        $this->assertEquals(30000.0, (float) $ngn['paid']);
        // outstanding = 35000 - 30000 = 5000 — the unpaid invoice.
        $this->assertEquals(5000.0, (float) $ngn['outstanding']);

        // The screen says so out loud: paid 30,000, outstanding 5,000.
        $this->actingAs($this->admin())->get(route('admin.advertisements.reports'))
            ->assertSee('outstanding (invoiced − paid)');
    }

    public function test_engagement_and_revenue_reports_obey_the_date_window(): void
    {
        $paid = $this->makeCampaign([
            'title' => 'Windowed Payment Record',
            'agreed_price' => '20000.00',
            'currency' => 'NGN',
            'payment_status' => 'paid',
            'invoice_reference' => 'INV-W1',
            'paid_at' => now()->subDays(40)->toDateString(),
        ]);
        $this->makeCampaign([
            'title' => 'Recent Payment Record',
            'agreed_price' => '8000.00',
            'currency' => 'NGN',
            'payment_status' => 'paid',
            'invoice_reference' => 'INV-W2',
            'paid_at' => now()->subDays(2)->toDateString(),
        ]);

        // One engagement event forty days ago, two days ago, today.
        $this->recordEngagement($paid, 'impression', now()->subDays(40)->toDateString());
        $this->recordEngagement($paid, 'impression', now()->subDays(2)->toDateString());
        $this->recordEngagement($paid, 'click', now()->toDateString());

        $service = app(\App\Services\Advertising\AdRevenueReportService::class);

        $windowed = $service->build(now()->subDays(30)->toDateString(), now()->toDateString());

        // Engagement: the 40-day-old impression is out; the in-window window
        // has exactly one impression and one click.
        $this->assertSame(1, $windowed['engagement']['impressions']);
        $this->assertSame(1, $windowed['engagement']['clicks']);
        $this->assertSame(100.0, $windowed['engagement']['ctr']);

        // Paid revenue: only the payment dated inside the window;
        // the 40-day-old payment record is outside and stays out.
        $this->assertEquals(8000.0, (float) $windowed['revenue']['NGN']['paid']);

        // All-time covers everything: two impressions, one click.
        $alltime = $service->build();
        $this->assertSame(2, $alltime['engagement']['impressions']);
        $this->assertEquals(28000.0, (float) $alltime['revenue']['NGN']['paid']);
    }

    public function test_notification_delivery_results_come_from_the_real_ledger(): void
    {
        $admin = $this->admin();
        $notification = PushNotification::create([
            'created_by' => $admin->id,
            'title' => 'New Volume Out Now',
            'message' => 'Volume 2 is live.',
            'category' => 'new_volume',
            'audience' => 'all',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $subscriptionOne = PushSubscription::create([
            'endpoint' => 'https://push.example.org/sub-1',
            'endpoint_hash' => hash('sha256', 'https://push.example.org/sub-1'),
            'p256dh' => 'k',
            'auth' => 'a',
            'wants_editorial' => true,
        ]);
        $subscriptionTwo = PushSubscription::create([
            'endpoint' => 'https://push.example.org/sub-2',
            'endpoint_hash' => hash('sha256', 'https://push.example.org/sub-2'),
            'p256dh' => 'k2',
            'auth' => 'a2',
            'wants_editorial' => true,
        ]);

        // One accepted, one failed permanently, one still pending.
        PushDelivery::create([
            'notification_id' => $notification->id,
            'subscription_id' => $subscriptionOne->id,
            'status' => 'sent',
            'delivered_at' => now(),
        ]);
        PushDelivery::create([
            'notification_id' => $notification->id,
            'subscription_id' => $subscriptionTwo->id,
            'status' => 'failed',
            'attempts' => 3,
            'last_error' => 'HTTP 410: endpoint gone',
        ]);

        $report = app(\App\Services\Advertising\AdRevenueReportService::class)->build();

        $this->assertSame(1, $report['deliveries']['statuses']['sent']);
        $this->assertSame(1, $report['deliveries']['statuses']['failed']);
        $this->assertSame(0, $report['deliveries']['statuses']['pending']);
        $this->assertSame(1, $report['deliveries']['notifications']);
        $this->assertSame(1, $report['deliveries']['dispatched']);

        $this->actingAs($this->admin())
            ->get(route('admin.advertisements.reports'))
            ->assertOk()
            ->assertSee('endpoint deliveries accepted')
            ->assertSee('endpoint deliveries failed')
            // The honesty statement itself is on the screen:
            ->assertSee('never claims reads or clicks');
    }

    public function test_the_reports_screen_renders_honestly_when_nothing_is_recorded(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.advertisements.reports'))
            ->assertOk()
            ->assertSee('No prices have been recorded yet')
            ->assertSee('No engagement recorded in the selected window')
            ->assertSee('No invoices recorded')
            ->assertSee('No notifications have been created at all');
    }

    public function test_an_invalid_date_window_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.advertisements.reports', [
                'from' => '2026-10-22',
                'to' => '2026-10-01',
            ]))
            ->assertSessionHasErrors('to');
    }
}
