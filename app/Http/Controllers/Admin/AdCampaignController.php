<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdPlacement;
use App\Models\AuditLog;
use App\Services\Advertising\AdCampaignService;
use App\Services\Advertising\AdRevenueReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Advertisements & Announcements manager.
 *
 * Server-side authorisation on every action: the route group carries the
 * role middleware and every method double-checks the specific permission
 * through the gate — the buttons shown in the interface are a convenience,
 * not the control. Campaign records are never deleted; they are archived.
 */
class AdCampaignController extends Controller
{
    public function __construct(
        private AdCampaignService $campaigns,
        private AdRevenueReportService $revenueReports,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('advertising.view');

        $search = trim((string) $request->get('q'));
        $status = (string) $request->get('status', '');
        $type = (string) $request->get('type', '');
        $placementId = (string) $request->get('placement', '');

        $listings = AdCampaign::with(['placement', 'creator'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$search}%")
                ->orWhere('advertiser_name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($placementId !== '', fn ($q) => $q->where('placement_id', $placementId))
            ->orderByRaw("CASE status
                WHEN 'published' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'pending' THEN 2
                WHEN 'draft' THEN 3 WHEN 'paused' THEN 4 WHEN 'expired' THEN 5
                ELSE 6 END")
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Upcoming and recent windows for the scheduling calendar strip.
        $calendarWindow = AdCampaign::with('placement')
            ->whereNotNull('starts_at')
            ->orWhereNotNull('ends_at')
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get()
            ->filter(fn (AdCampaign $c) => $c->starts_at !== null || $c->ends_at !== null);

        $placements = AdPlacement::orderBy('sort')->get();
        $statusCounts = AdCampaign::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('auth.admin.advertisements.index', compact(
            'listings', 'placements', 'statusCounts', 'calendarWindow',
            'search', 'status', 'type', 'placementId',
        ));
    }

    public function create(): View
    {
        Gate::authorize('advertising.manage');

        $placements = AdPlacement::where('is_active', true)->orderBy('sort')->get();

        return view('auth.admin.advertisements.create', compact('placements'));
    }

    public function store(Request $request)
    {
        Gate::authorize('advertising.manage');

        $validated = $this->validateCampaign($request);

        $campaign = AdCampaign::create($this->campaignPayload($request, $validated, auth()->id()));

        $this->audit($request, $campaign, 'advertising.campaign.created');

        return redirect()
            ->route('admin.advertisements.show', $campaign->fresh())
            ->with('success', 'Campaign created.');
    }

    public function show(AdCampaign $advertisement)
    {
        Gate::authorize('advertising.view');

        $advertisement->load('placement', 'creator');

        // The preview renders through the same partial the public pages use.
        $impressionCount = $advertisement->events()->where('event', 'impression')->count();
        $clickCount = $advertisement->events()->where('event', 'click')->count();
        $daily = $advertisement->events()
            ->selectRaw("occurred_on,
                sum(case when event = 'impression' then 1 else 0 end) as impressions,
                sum(case when event = 'click' then 1 else 0 end) as clicks")
            ->groupBy('occurred_on')
            ->orderByDesc('occurred_on')
            ->limit(30)
            ->get();

        return view('auth.admin.advertisements.show', compact(
            'advertisement', 'impressionCount', 'clickCount', 'daily',
        ));
    }

    public function edit(AdCampaign $advertisement)
    {
        Gate::authorize('advertising.manage');

        $placements = AdPlacement::orderBy('sort')->get();

        return view('auth.admin.advertisements.edit', [
            'campaign' => $advertisement,
            'placements' => $placements,
        ]);
    }

    public function update(Request $request, AdCampaign $advertisement)
    {
        Gate::authorize('advertising.manage');

        $validated = $this->validateCampaign($request, $advertisement);

        // Recorded before/after for the audit trail: whatever was edited,
        // including revenue columns, is traceable per the brief.
        $before = $advertisement->withoutRelations()->only(array_keys($validated));

        $advertisement->update($this->campaignPayload($request, $validated, $advertisement->created_by));

        $this->audit($request, $advertisement->fresh(), 'advertising.campaign.updated', [
            'before' => $before,
            'after' => $advertisement->withoutRelations()->only(array_keys($validated)),
        ]);

        return redirect()
            ->route('admin.advertisements.show', $advertisement->fresh())
            ->with('success', 'Campaign updated.');
    }

    public function transition(Request $request, AdCampaign $advertisement)
    {
        Gate::authorize('advertising.manage');

        $validated = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $fromStatus = $advertisement->status;
        $this->campaigns->transition($advertisement, $validated['status'], auth()->id());

        $this->audit($request, $advertisement->fresh(), 'advertising.campaign.status_changed', [
            'from' => $fromStatus,
            'to' => $advertisement->fresh()->status,
        ]);

        return redirect()
            ->route('admin.advertisements.show', $advertisement->fresh())
            ->with('success', 'Campaign is now '.(AdCampaign::STATUSES[$advertisement->fresh()->status] ?? $validated['status']).'.');
    }

    public function placements(Request $request)
    {
        Gate::authorize('advertising.configure');

        $placements = AdPlacement::orderBy('sort')->get();

        return view('auth.admin.advertisements.placements', compact('placements'));
    }

    public function updatePlacement(Request $request)
    {
        Gate::authorize('advertising.configure');

        $validated = $request->validate([
            'placement_id' => ['required', 'exists:ad_placements,id'],
            'label' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);

        $placement = AdPlacement::findOrFail($validated['placement_id']);
        $before = $placement->only(['label', 'is_active']);
        $placement->update([
            'label' => $validated['label'],
            'is_active' => $validated['is_active'],
        ]);

        // A placement deactivation hides a public slot everywhere — it is a
        // configuration change on the public site, so it belongs in the audit
        // trail like campaign changes do.
        AuditLog::create([
            'user_id' => $request->user()?->getKey(),
            'event' => 'advertising.placement.updated',
            'auditable_type' => AdPlacement::class,
            'auditable_id' => $placement->id,
            'new_values' => [
                'before' => $before,
                'after' => $placement->only(['label', 'is_active']),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('admin.advertisements.placements')
            ->with('success', 'Placement updated.');
    }

    /**
     * Revenue and performance reports (Addendum Module 4). Financial data is
     * its own authorization boundary: `advertising.reports` is deliberately
     * not part of the broader `advertising.manage` grant, and the gate below
     * is checked on every request regardless of the interface.
     */
    public function reports(Request $request): View
    {
        Gate::authorize('advertising.reports');

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $report = $this->revenueReports->build(
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        return view('auth.admin.advertisements.reports', [
            'report' => $report,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ]);
    }

    /* ─────────────── validation ─────────────── */

    private function validateCampaign(Request $request, ?AdCampaign $existing = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'in:'.implode(',', array_keys(AdCampaign::TYPES))],
            'advertiser_name' => ['nullable', 'string', 'max:160'],
            'placement_id' => ['required', 'exists:ad_placements,id'],
            'destination_url' => ['nullable', 'string', 'max:500'],
            'external_media_url' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['nullable', 'integer', 'between:0,1000'],
            'audience' => ['required', 'in:'.implode(',', array_keys(AdCampaign::AUDIENCES))],
            'notify_push' => ['nullable', 'boolean'],
            'label' => ['required', 'string', 'max:40'],
            'sponsor_fee_note' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'submit_status' => ['nullable', 'in:draft,publish'],
            'media' => ['nullable', 'file', 'max:51200'],
            // Revenue controls (Addendum Module 4) — all optional.
            'agreed_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'agreement_reference' => ['nullable', 'string', 'max:120'],
            'invoice_reference' => ['nullable', 'string', 'max:120'],
            'payment_status' => ['nullable', 'in:'.implode(',', array_keys(AdCampaign::PAYMENT_STATUSES))],
            'paid_at' => ['nullable', 'date'],
        ]);

        // External media and destinations must be plain web addresses; a
        // javascript:, data: or any other scheme is refused outright.
        foreach (['destination_url', 'external_media_url'] as $urlField) {
            if (! empty($validated[$urlField])) {
                $scheme = strtolower((string) parse_url($validated[$urlField], PHP_URL_SCHEME));
                if (! in_array($scheme, ['http', 'https'], true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $urlField => 'Only http(s) addresses are accepted.',
                    ]);
                }
            }
        }

        // Revenue controls are their own authorization boundary, checked
        // deliberately: advertising.manage edits campaigns;
        // advertising.reports is the financial role and only holders of it
        // may write money onto a record.
        if ($request->hasAny(['agreed_price', 'currency', 'agreement_reference', 'invoice_reference', 'payment_status', 'paid_at'])) {
            Gate::authorize('advertising.reports');
        }

        // Server-side consistency: money is recorded as a state machine, not
        // as free text. The effective state is the request's values or, on an
        // update where a revenue field was not posted, the field already on
        // the record. An invoice without an invoice number, a payment
        // without a date, or a payment state without a recorded price is a
        // bookkeeping fiction and is refused — nothing is auto-derived.
        $existing = $request->route('advertisement');
        $effective = fn (string $key) => array_key_exists($key, $validated)
            ? $validated[$key]
            : ($existing?->{$key} ?? null);

        $price = $effective('agreed_price');
        $paymentStatus = $effective('payment_status');
        $invoice = $effective('invoice_reference');
        $paidAt = $effective('paid_at');

        if ($paymentStatus !== null && ($price === null || $price === '')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_status' => 'Record the agreed price before recording an invoice or payment state.',
            ]);
        }

        if ($paymentStatus === 'invoiced' && ($invoice === null || $invoice === '')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'invoice_reference' => 'A record marked "Invoiced" needs its invoice reference.',
            ]);
        }

        if ($paymentStatus === 'paid' && ($paidAt === null || $paidAt === '')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'paid_at' => 'A record marked "Paid" needs its payment date.',
            ]);
        }

        if ($paymentStatus !== 'paid' && $paidAt !== null && $paidAt !== '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'paid_at' => 'A payment date may only be recorded once the status is "Paid".',
            ]);
        }

        if (($price !== null && $price !== '') && ($validated['currency'] ?? null) === null && !$existing?->currency) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'currency' => 'A recorded price needs its currency (three letters, e.g. NGN).',
            ]);
        }

        if ($validated['currency'] ?? false) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        if ($validated['currency'] ?? false) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        // Media typing follows the campaign: an image/banner takes an image,
        // a video takes a video, an audio promotion takes an audio file.
        // Uploads are stored under hashed names; nothing uploaded is ever
        // executed, and no untrusted markup is ever embedded in public pages.
        if ($request->hasFile('media')) {
            $media = $request->file('media');
            $type = $validated['type'];

            $rulesByKind = [
                'image' => ['mimes:jpg,jpeg,png,webp,gif', 'mimetypes:image/jpeg,image/png,image/webp,image/gif', 'max:8192'],
                'video' => ['mimes:mp4,webm,ogv', 'mimetypes:video/mp4,video/webm,video/ogg', 'max:51200'],
                'audio' => ['mimes:mp3,wav,ogg,m4a', 'mimetypes:audio/mpeg,audio/wav,audio/ogg', 'max:20480'],
            ];

            $kind = in_array($type, ['video'], true) ? 'video'
                : (in_array($type, ['audio'], true) ? 'audio' : 'image');

            $request->validate(['media' => $rulesByKind[$kind]]);

            $validated['media_type'] = $kind;
        }

        return $validated;
    }

    private function campaignPayload(Request $request, array $validated, ?int $userId): array
    {
        $payload = [
            'placement_id' => $validated['placement_id'],
            'created_by' => $userId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'advertiser_name' => $validated['advertiser_name'] ?? null,
            'external_media_url' => $validated['external_media_url'] ?? null,
            'destination_url' => $validated['destination_url'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'priority' => $validated['priority'] ?? 0,
            'audience' => $validated['audience'],
            'notify_push' => $request->boolean('notify_push'),
            'label' => $validated['label'],
            'sponsor_fee_note' => $validated['sponsor_fee_note'] ?? null,
            'internal_notes' => $validated['internal_notes'] ?? null,
        ];

        // Revenue keys enter the payload only when the acting user holds the
        // financial gate. Anyone else — including a manage-only role editing
        // the rest of the campaign — leaves recorded money untouched.
        if ($request->hasAny(['agreed_price', 'currency', 'agreement_reference', 'invoice_reference', 'payment_status', 'paid_at'])) {
            Gate::authorize('advertising.reports');

            $payload = array_merge($payload, [
                'agreed_price' => $validated['agreed_price'] ?? null,
                'currency' => $validated['currency'] ?? null,
                'agreement_reference' => $validated['agreement_reference'] ?? null,
                'invoice_reference' => $validated['invoice_reference'] ?? null,
                'payment_status' => $validated['payment_status'] ?? null,
                'paid_at' => $validated['paid_at'] ?? null,
            ]);
        }

        if ($request->hasFile('media')) {
            $previous = $request->route('advertisement')?->media_path ?? null;
            $payload['media_path'] = $request->file('media')->store('ad-media', 'public');
            $payload['media_type'] = $validated['media_type'];

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        }

        // A campaign approved at save time starts as scheduled when its
        // window has not opened yet; the public window check makes it live at
        // the recorded moment without any worker. A window that has already
        // closed is recorded as expired for the archive, never shown.
        if (($validated['submit_status'] ?? '') === 'publish') {
            $payload['status'] = $this->statusForApproved($payload);
        }

        return $payload;
    }

    private function statusForApproved(array $payload): string
    {
        $now = now();

        if ($payload['starts_at'] !== null && $payload['starts_at'] > $now) {
            return 'scheduled';
        }

        if ($payload['ends_at'] !== null && $payload['ends_at'] < $now) {
            return 'expired';
        }

        return 'published';
    }

    /**
     * Campaign audit trail (Module 6 consolidation): every create, edit and
     * status change is recorded to the same `audit_logs` stream the push
     * composer and the scheduler use, with the acting user, IP and agent.
     */
    private function audit(Request $request, AdCampaign $campaign, string $event, array $values = []): void
    {
        // Effective state, not the request's guess: a freshly created
        // campaign may derive its status (or other columns) from database
        // defaults, so the trail reads the record as stored.
        $campaign->refresh();

        AuditLog::create([
            'user_id' => $request->user()?->getKey(),
            'event' => $event,
            'auditable_type' => AdCampaign::class,
            'auditable_id' => $campaign->id,
            'new_values' => $values === [] ? $campaign->only([
                'title', 'type', 'placement_id', 'starts_at', 'ends_at', 'status', 'label',
            ]) : $values,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
