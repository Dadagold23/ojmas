<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IssueSchedule;
use App\Models\Volume;
use App\Services\Planning\IssueScheduleService;
use App\Services\Planning\ScheduleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Issue & announcement scheduling (Addendum Module 5).
 *
 * Every action carries its own permission gate over the route-role
 * middleware, and every mutation is audited by the service. Scheduling a
 * publication is planning, never publishing: no action here sets an issue
 * or article status.
 */
class IssueScheduleController extends Controller
{
    public function __construct(
        private IssueScheduleService $planner,
        private ScheduleCalendarService $calendar,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('scheduling.view');

        $calendar = $this->calendar->month(
            (int) $request->get('y', now()->year),
            (int) $request->get('m', now()->month),
        );

        $schedules = IssueSchedule::with(['volume', 'issue', 'campaign', 'notification', 'creator'])
            ->orderByRaw("CASE status
                WHEN 'scheduled' THEN 0 WHEN 'draft' THEN 1 WHEN 'paused' THEN 2 ELSE 3 END")
            ->orderBy('announce_at')
            ->paginate(10)
            ->withQueryString();

        return view('auth.admin.schedules.index', compact('schedules', 'calendar'));
    }

    public function create(): View
    {
        Gate::authorize('scheduling.manage');

        return view('auth.admin.schedules.create', $this->formVariables());
    }

    public function store(Request $request)
    {
        Gate::authorize('scheduling.manage');

        $validated = $this->validateSchedule($request);

        $schedule = $this->planner->plan($validated + ['created_by' => $request->user()->id], $request);

        return redirect()
            ->route('admin.schedules.show', $schedule)
            ->with('success', 'Issue schedule recorded.');
    }

    public function show(IssueSchedule $schedule, Request $request): View
    {
        Gate::authorize('scheduling.view');

        $schedule->load(['volume', 'issue', 'campaign.placement', 'notification', 'creator']);

        // The audit trail around this plan and its announcement surfaces:
        // the schedule's own entries plus whatever was recorded for the
        // linked campaign and notification.
        $subjects = collect([['App\Models\IssueSchedule', $schedule->id]]);
        if ($schedule->linked_campaign_id) {
            $subjects->push(['App\Models\AdCampaign', $schedule->linked_campaign_id]);
        }
        if ($schedule->linked_notification_id) {
            $subjects->push(['App\Models\PushNotification', $schedule->linked_notification_id]);
        }

        $trail = \App\Models\AuditLog::query()
            ->where(function ($q) use ($subjects) {
                foreach ($subjects as [$type, $id]) {
                    $q->orWhere(fn ($w) => $w->where('auditable_type', $type)->where('auditable_id', $id));
                }
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('auth.admin.schedules.show', compact('schedule', 'trail'));
    }

    public function edit(IssueSchedule $schedule): View
    {
        Gate::authorize('scheduling.manage');

        return view('auth.admin.schedules.edit', array_merge(
            $this->formVariables(),
            ['schedule' => $schedule],
        ));
    }

    public function update(Request $request, IssueSchedule $schedule)
    {
        Gate::authorize('scheduling.manage');

        $validated = $this->validateSchedule($request, $schedule);

        $this->planner->updatePlan($schedule, $validated, $request);

        return redirect()
            ->route('admin.schedules.show', $schedule->fresh())
            ->with('success', 'Schedule updated; reversible announcement surfaces were kept in step.');
    }

    public function pause(Request $request, IssueSchedule $schedule)
    {
        Gate::authorize('scheduling.manage');

        $this->planner->pause($schedule, $request);

        return redirect()->route('admin.schedules.show', $schedule->fresh())
            ->with('success', 'Announcement paused; the queued notification was cancelled (already-sent notifications cannot be recalled).');
    }

    public function resume(Request $request, IssueSchedule $schedule)
    {
        Gate::authorize('scheduling.manage');

        $this->planner->resume($schedule, $request);

        return redirect()->route('admin.schedules.show', $schedule->fresh())
            ->with('success', 'Announcement resumed; a fresh notification was scheduled where the queued one had been cancelled.');
    }

    public function cancel(Request $request, IssueSchedule $schedule)
    {
        Gate::authorize('scheduling.manage');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->planner->cancel($schedule, $request, $validated['reason'] ?? '');

        return redirect()->route('admin.schedules.show', $schedule->fresh())
            ->with('success', 'Schedule cancelled; announcement surfaces archived or cancelled accordingly.');
    }

    /* ─────────────── validation ─────────────── */

    private function formVariables(): array
    {
        return [
            'volumes' => Volume::orderByDesc('year')->orderBy('volume_no')->get(['id', 'volume_no', 'year', 'title']),
        ];
    }

    private function validateSchedule(Request $request, ?IssueSchedule $existing = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:300'],
            'announcement_type' => ['required', 'in:issue_announcement,call_for_papers'],
            'volume_id' => ['nullable', 'exists:volumes,id'],
            'new_volume_no' => ['nullable', 'integer', 'min:1', 'max:200'],
            'new_volume_year' => ['nullable', 'digits:4'],
            'issue_no' => ['nullable', 'string', 'max:50'],
            'planned_publication_date' => ['nullable', 'date'],
            'cfp_opens_at' => ['nullable', 'date'],
            'submission_deadline' => ['nullable', 'date', 'after_or_equal:cfp_opens_at'],
            'announce_at' => ['nullable', 'date'],
            'announcement_ends_at' => ['nullable', 'date', 'after_or_equal:announce_at'],
            'landing_url' => ['nullable', 'string', 'max:500'],
            'banner' => ['nullable', 'image', 'max:8192'],
            'notify_push' => ['nullable', 'boolean'],
            'submit_action' => ['nullable', 'in:draft,schedule'],
        ]);

        // A plan anchors on a volume: an existing one, or a genuinely new
        // number (which is matched-or-created, never duplicated).
        if (blank($validated['volume_id'] ?? null) && blank($validated['new_volume_no'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'volume_id' => 'Pick an existing volume, or record the new volume number and year.',
            ]);
        }

        if (! blank($validated['new_volume_no'] ?? null) && blank($validated['new_volume_year'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'new_volume_year' => 'A new volume needs its year.',
            ]);
        }

        // An issue number anchors the schedule; the service matches (never
        // duplicates) or creates a draft placeholder issue for the editors.
        if (blank($validated['issue_no'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'issue_no' => 'Record the issue number this schedule plans.',
            ]);
        }

        if (($validated['submit_action'] ?? 'draft') === 'schedule' && blank($validated['announce_at'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'announce_at' => 'Scheduling an announcement needs its publication time.',
            ]);
        }

        // Announcements only carry plain web addresses.
        if (! blank($validated['landing_url'] ?? null)) {
            $scheme = strtolower((string) parse_url($validated['landing_url'], PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'landing_url' => 'Only http(s) addresses are accepted.',
                ]);
            }
        }

        if ($request->hasFile('banner')) {
            $validated['banner_path'] = $request->file('banner')->store('ad-media', 'public');

            // A replaced banner for an existing schedule removes the old
            // file only once the new one is on record.
            if ($existing?->banner_path && $existing->banner_path !== $validated['banner_path']) {
                Storage::disk('public')->delete($existing->banner_path);
            }
        }
        unset($validated['banner']);

        // Editing keeps the recorded announcement time unless a new one was
        // posted, so an accidental blank field cannot silently unschedule.
        if (($validated['submit_action'] ?? 'draft') === 'schedule'
            && blank($validated['announce_at'] ?? null) && $existing?->announce_at) {
            $validated['announce_at'] = $existing->announce_at->format('Y-m-d\TH:i');
        }

        return $validated;
    }
}
