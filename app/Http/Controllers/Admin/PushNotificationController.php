<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchPushNotification;
use App\Models\AdCampaign;
use App\Models\AuditLog;
use App\Models\PushCategorySetting;
use App\Models\PushDelivery;
use App\Models\PushNotification;
use App\Services\Push\PushDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The push notification composer and reporting screens.
 *
 * Server-side authorisation on every action (route group middleware plus a
 * per-method gate). Categories eligibility is an administrator decision
 * stored in `push_category_settings`; a non-eligible category can never
 * enter public dispatch even if a notification was created for it.
 */
class PushNotificationController extends Controller
{
    public function __construct(private PushDispatchService $push) {}

    public function index(Request $request): View
    {
        Gate::authorize('push.view');

        $search = trim((string) $request->get('q'));
        $status = (string) $request->get('status');
        $category = (string) $request->get('category');

        $listings = PushNotification::with('creator')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->orderByRaw("CASE status
                WHEN 'sending' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'draft' THEN 2
                WHEN 'sent' THEN 3 WHEN 'failed' THEN 4 ELSE 5 END")
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Delivery status per notification — only what Web Push can actually
        // observe (per-endpoint HTTP acceptance) is ever reported.
        $deliverySummary = PushDelivery::query()
            ->selectRaw("notification_id,
                count(*) as total,
                sum(case when status = 'sent' then 1 else 0 end) as sent,
                sum(case when status = 'failed' then 1 else 0 end) as failed,
                sum(case when status = 'pending' then 1 else 0 end) as pending")
            ->groupBy('notification_id')
            ->get()
            ->keyBy('notification_id');

        $publicKey = (string) config('push.vapid.public_key');
        $queueDriver = (string) config('queue.default');

        return view('auth.admin.push.index', compact(
            'listings', 'deliverySummary', 'search', 'status', 'category',
            'publicKey', 'queueDriver',
        ));
    }

    public function create(): View
    {
        Gate::authorize('push.manage');

        return view('auth.admin.push.create', $this->formVariables());
    }

    public function store(Request $request)
    {
        Gate::authorize('push.manage');

        $validated = $this->validateNotification($request);

        $notification = PushNotification::create(array_merge($validated, [
            'created_by' => auth()->id(),
            'status' => 'draft',
        ]));

        $this->audit($request, $notification, 'push.notification.created');

        return $this->respond($request, $notification);
    }

    /* ── actions after creation ── */

    public function send(Request $request, PushNotification $notification)
    {
        Gate::authorize('push.manage');

        $validated = $request->validate([
            'when' => ['required', 'in:now,schedule'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($validated['when'] === 'schedule') {
            $scheduledAt = $validated['scheduled_at'] ?? null;
            if ($scheduledAt === null) {
                return back()->withErrors(['scheduled_at' => 'A scheduled dispatch needs a publication time.']);
            }

            $notification->update(['status' => 'scheduled', 'scheduled_at' => $scheduledAt]);
            $this->audit($request, $notification, 'push.notification.scheduled');

            return redirect()->route('admin.push.show', $notification)
                ->with('success', 'Scheduled: the host cron (push:dispatch) will send it at the recorded time.');
        }

        // Immediate dispatch: staged then handed to the queue (sync driver
        // runs inline so no dependency on the unverified worker).
        $notification->update(['status' => 'sending']);

        if (config('push.queue')) {
            DispatchPushNotification::dispatch($notification->fresh());

            return redirect()->route('admin.push.show', $notification->fresh())
                ->with('success', 'Dispatch queued.');
        }

        $staged = $this->push->stageDeliveries($notification->fresh());

        if ($staged === 0) {
            $notification->update(['status' => 'draft']);

            return redirect()->route('admin.push.show', $notification)
                ->withErrors(['push' => 'No active subscription accepted this audience/category — nothing was sent.']);
        }

        $result = $this->push->dispatch($notification->fresh());
        $this->audit($request, $notification->fresh(), 'push.notification.sent');
        $this->linkCampaign($notification->fresh());

        return redirect()->route('admin.push.show', $notification->fresh())
            ->with('success', "Dispatched: {$result['sent']} sent, {$result['failed']} failed, {$result['disabled']} subscription(s) disabled.");
    }

    public function cancel(Request $request, PushNotification $notification)
    {
        Gate::authorize('push.manage');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $notification->update(['status' => 'cancelled']);
        $this->audit($request, $notification, 'push.notification.cancelled', ['reason' => $validated['reason'] ?? null]);

        return redirect()->route('admin.push.show', $notification)
            ->with('success', 'Notification cancelled.');
    }

    public function retry(Request $request, PushNotification $notification)
    {
        Gate::authorize('push.manage');

        $notification->update([
            'status' => 'scheduled',
            'scheduled_at' => now(), // due immediately
        ]);

        $this->audit($request, $notification, 'push.notification.retry_queued');

        return redirect()->route('admin.push.show', $notification)
            ->with('success', 'Marked for immediate retry; run push:dispatch (crons and retries share the same dedupe).');
    }

    /* ── delivery report + preview ── */

    public function show(Request $request, PushNotification $notification): View
    {
        Gate::authorize('push.view');

        $notification->load('creator', 'campaign');

        $deliveries = $notification->deliveries()
            ->selectRaw("status, count(*) as total, sum(attempts) as attempts")
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $recent = $notification->deliveries()
            ->with('subscription:id,endpoint,is_active,failure_count')
            ->latest('id')
            ->limit(30)
            ->get();

        $audit = AuditLog::query()
            ->where('auditable_type', PushNotification::class)
            ->where('auditable_id', $notification->id)
            ->with('user')
            ->latest('id')
            ->limit(20)
            ->get();

        $publicKey = (string) config('push.vapid.public_key');
        $queueDriver = (string) config('queue.default');

        return view('auth.admin.push.show', array_merge(
            compact('notification', 'deliveries', 'recent', 'audit', 'publicKey', 'queueDriver'),
            $this->formVariables(),
        ));
    }

    public function preview(Request $request, PushNotification $notification): View
    {
        Gate::authorize('push.view');

        return view('auth.admin.push.preview', [
            'notification' => $notification,
        ]);
    }

    /* ── category eligibility configuration ── */

    public function categories(): View
    {
        Gate::authorize('push.configure');

        $settings = PushCategorySetting::query()->orderBy('sort')->get();

        return view('auth.admin.push.categories', compact('settings'));
    }

    public function updateCategories(Request $request)
    {
        Gate::authorize('push.configure');

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.id' => ['required', 'exists:push_category_settings,id'],
            'settings.*.is_eligible' => ['required', 'boolean'],
        ]);

        foreach ($validated['settings'] as $row) {
            $setting = PushCategorySetting::find($row['id']);
            if ($setting && $setting->is_eligible !== (bool) $row['is_eligible']) {
                $setting->update(['is_eligible' => (bool) $row['is_eligible']]);
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => 'push.categories.updated',
            'auditable_type' => PushCategorySetting::class,
            'auditable_id' => (int) ($validated['settings'][0]['id'] ?? 0),
            'new_values' => ['settings' => $validated['settings']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.push.categories')->with('success', 'Category eligibility saved.');
    }

    /* ── helpers ── */

    private function validateNotification(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:300'],
            'category' => ['required', 'in:'.implode(',', array_keys(PushNotification::CATEGORIES))],
            'action_url' => ['nullable', 'string', 'max:500'],
            'audience' => ['required', 'in:'.implode(',', array_keys(PushNotification::AUDIENCES))],
            'ad_campaign_id' => ['nullable', 'exists:ad_campaigns,id'],
            'icon' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'submit_action' => ['required', 'in:draft,schedule,send_now'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'expires_at' => ['nullable', 'date', 'after:scheduled_at'],
        ]);

        if (! empty($validated['action_url'])) {
            $scheme = strtolower((string) parse_url($validated['action_url'], PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'action_url' => 'Only http(s) destinations are accepted.',
                ]);
            }
        }

        if ($request->hasFile('icon')) {
            $validated['icon_path'] = $request->file('icon')->store('push-icons', 'public');
        }
        unset($validated['icon'], $validated['scheduled_at'], $validated['expires_at']);

        return $validated;
    }

    private function respond(Request $request, PushNotification $notification)
    {
        $submit = $request->input('submit_action', 'draft');

        if ($submit === 'send_now') {
            return $this->sendNowFromStore($notification);
        }

        if ($submit === 'schedule') {
            $scheduledAt = $request->input('scheduled_at');

            if (blank($scheduledAt)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'scheduled_at' => 'A scheduled notification needs a publication time.',
                ]);
            }

            $notification->update(['status' => 'scheduled', 'scheduled_at' => $scheduledAt]);

            return redirect()->route('admin.push.show', $notification->fresh())
                ->with('success', 'Notification scheduled.');
        }

        return redirect()->route('admin.push.show', $notification->fresh())
            ->with('success', 'Notification saved as draft.');
    }

    private function sendNowFromStore(PushNotification $notification)
    {
        $notification->update(['status' => 'sending']);

        if (config('push.queue')) {
            if ($staged = $this->push->stageDeliveries($notification->fresh())) {
                DispatchPushNotification::dispatch($notification->fresh());

                return redirect()->route('admin.push.show', $notification->fresh())
                    ->with('success', "Staged {$staged} delivery(-ies); dispatch queued.");
            }
        } else {
            $staged = $this->push->stageDeliveries($notification->fresh());

            if ($staged > 0) {
                $result = $this->push->dispatch($notification->fresh());
                $this->linkCampaign($notification->fresh());

                return redirect()->route('admin.push.show', $notification->fresh())
                    ->with('success', "Dispatched: {$result['sent']} sent, {$result['failed']} failed, {$result['disabled']} subscription(s) disabled.");
            }
        }

        $notification->update(['status' => 'draft']);

        return redirect()->route('admin.push.show', $notification)
            ->withErrors(['push' => 'No active subscription matched (or the category is not push-eligible) — nothing was sent.']);
    }

    private function formVariables(): array
    {
        $campaigns = AdCampaign::query()
            ->whereIn('status', ['published', 'scheduled', 'paused'])
            ->orderByDesc('id')
            ->get(['id', 'title']);

        $categories = PushCategorySetting::query()->orderBy('sort')->get()->mapWithKeys(function ($setting) {
            return [$setting->category => ($setting->is_eligible ? '' : '[not push-eligible] ').$setting->label];
        });

        return compact('campaigns', 'categories');
    }

    private function audit(Request $request, PushNotification $notification, string $event, array $values = []): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->getKey(),
            'event' => $event,
            'auditable_type' => PushNotification::class,
            'auditable_id' => $notification->id,
            'new_values' => $values === [] ? $notification->only([
                'title', 'message', 'category', 'audience', 'status', 'scheduled_at',
            ]) : $values,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Records the campaign ↔ notification link promised in Module 2: the
     * reserved `ad_campaigns.push_campaign_id` is filled once a linked
     * notification has actually been dispatched (never before).
     */
    private function linkCampaign(PushNotification $notification): void
    {
        if ($notification->ad_campaign_id === null) {
            return;
        }

        $campaign = AdCampaign::find($notification->ad_campaign_id);

        if ($campaign !== null) {
            $campaign->update(['push_campaign_id' => $notification->id]);
        }
    }
}
