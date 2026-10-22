@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .sched-hero { border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(13,110,253,.92), rgba(15,23,42,.92));
        color:#fff; box-shadow: 0 20px 50px rgba(15,23,42,.16); }

    .status-chip { border-radius: 999px; padding: .3rem .7rem; font-size: .75rem; font-weight: 600; }
    .status-scheduled { background: #dbeafe; color: #1e40af; }
    .status-draft { background: #e2e8f0; color: #334155; }
    .status-paused { background: #fce7f3; color: #9d174d; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }
    .status-sent { background: #d1fae5; color: #065f46; }
    .status-announced-note { background: #fff7ed; color: #9a3412; }
</style>

<div class="container py-4">
    <div class="card sched-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-2">
                        Vol {{ $schedule->volume?->volume_no ?? '—' }} · Issue {{ $schedule->issue?->issue_no ?? '—' }}
                        <span class="status-chip status-{{ $schedule->status }} ms-2">{{ \App\Models\IssueSchedule::STATUSES[$schedule->status] ?? $schedule->status }}</span>
                    </h2>
                    <p class="mb-0 text-white-50">{{ \Illuminate\Support\Str::limit($schedule->title, 90) }}</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    @can('scheduling.manage')
                        <div class="d-flex flex-column gap-2 align-items-lg-end">
                            <a href="{{ route('admin.schedules.edit', $schedule) }}" class="btn btn-warning fw-semibold">
                                <i class="bi bi-pencil me-1"></i>Edit schedule
                            </a>
                            @if(in_array($schedule->status, [\App\Models\IssueSchedule::STATUS_SCHEDULED, \App\Models\IssueSchedule::STATUS_DRAFT], true))
                                <form method="POST" action="{{ route('admin.schedules.pause', $schedule) }}">
                                    @csrf
                                    <button class="btn btn-outline-light"><i class="bi bi-pause me-1"></i>Pause announcement</button>
                                </form>
                            @endif
                            @if($schedule->status === \App\Models\IssueSchedule::STATUS_PAUSED)
                                <form method="POST" action="{{ route('admin.schedules.resume', $schedule) }}">
                                    @csrf
                                    <button class="btn btn-outline-light"><i class="bi bi-play me-1"></i>Resume announcement</button>
                                </form>
                            @endif
                            @if($schedule->status !== \App\Models\IssueSchedule::STATUS_CANCELLED)
                                <button class="btn btn-outline-light" data-bs-toggle="collapse" data-bs-target="#cancelBox"><i class="bi bi-x-circle me-1"></i>Cancel schedule</button>
                            @endif
                        </div>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
            {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($schedule->status !== \App\Models\IssueSchedule::STATUS_CANCELLED)
        <div class="collapse mb-4" id="cancelBox">
            <div class="card panel-card border-danger">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('admin.schedules.cancel', $schedule) }}">
                        @csrf
                        <label class="form-label fw-semibold">Reason for cancelling <span class="text-muted fw-normal">(recorded in the audit trail)</span></label>
                        <input type="text" name="reason" class="form-control mb-3" maxlength="1000">
                        <button class="btn btn-danger">Cancel schedule and queued notifications</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card panel-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1"><i class="bi bi-calendar3-event me-1"></i>Recorded plan</h5>
                    <p class="text-muted mb-0">Exactly the dates entered when planning this issue. A scheduled announcement never publishes the issue or its articles.</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <dl class="row mb-0">
                        <dt class="col-6 small text-muted">Volume</dt>
                        <dd class="col-6 small mb-2">Vol {{ $schedule->volume?->volume_no ?? '—' }} ({{ $schedule->volume?->year }}) — {{ $schedule->volume?->status }}</dd>
                        <dt class="col-6 small text-muted">Issue</dt>
                        <dd class="col-6 small mb-2">
                            Issue {{ $schedule->issue?->issue_no ?? '—' }} — {{ $schedule->issue?->status }}
                            @if($schedule->issue)
                                <a href="{{ route('editor.issues.show', $schedule->issue) }}">open in editorial</a>
                            @endif
                        </dd>
                        <dt class="col-6 small text-muted">Announcement type</dt>
                        <dd class="col-6 small mb-2">{{ \App\Models\IssueSchedule::ANNOUNCEMENT_TYPES[$schedule->announcement_type] ?? $schedule->announcement_type }}</dd>
                        <dt class="col-6 small text-muted">Announce at</dt>
                        <dd class="col-6 small mb-2">{{ $schedule->announce_at?->format('D, d M Y H:i') ?? 'not scheduled yet' }}</dd>
                        <dt class="col-6 small text-muted">Announcement window ends</dt>
                        <dd class="col-6 small mb-2">{{ $schedule->announcement_ends_at?->format('D, d M Y H:i') ?? '—' }}</dd>
                        <dt class="col-6 small text-muted">Planned publication date</dt>
                        <dd class="col-6 small mb-2">{{ $schedule->planned_publication_date?->format('D, d M Y') ?? '—' }}</dd>
                        <dt class="col-6 small text-muted">Call-for-papers opens</dt>
                        <dd class="col-6 small mb-2">{{ $schedule->cfp_opens_at?->format('D, d M Y') ?? '—' }}</dd>
                        <dt class="col-6 small text-muted">Submission deadline</dt>
                        <dd class="col-6 small mb-2">{{ $schedule->submission_deadline?->format('D, d M Y') ?? '—' }}</dd>
                        <dt class="col-6 small text-muted">Message</dt>
                        <dd class="col-6 small mb-0">{{ $schedule->message ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card panel-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1"><i class="bi bi-broadcast me-1"></i>Announcement surfaces</h5>
                    <p class="text-muted mb-0">What scheduling actually prepares — and what it deliberately never touches.</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold small">Campaign window</div>
                                @if($schedule->campaign)
                                    <div class="small text-muted">
                                        {{ \App\Models\AdCampaign::STATUSES[$schedule->campaign->status] ?? $schedule->campaign->status }}
                                        · {{ $schedule->campaign->starts_at?->format('d M Y H:i') ?? '—' }} → {{ $schedule->campaign->ends_at?->format('d M Y H:i') ?? '—' }}
                                    </div>
                                    <a class="small" href="{{ route('admin.advertisements.show', $schedule->campaign) }}">Open campaign record</a>
                                @else
                                    <div class="small text-muted">Not prepared yet — the announcement is created when the schedule is published.</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 mb-3">
                        <div class="fw-semibold small">Queued push notification</div>
                        @if($schedule->notification)
                            <div class="small text-muted">
                                {{ \App\Models\PushNotification::STATUSES[$schedule->notification->status] ?? $schedule->notification->status }}
                                @if($schedule->notification->scheduled_at) · dispatch {{ $schedule->notification->scheduled_at->format('d M Y H:i') }} @endif
                            </div>
                            <a class="small" href="{{ route('admin.push.show', $schedule->notification) }}">Open notification record</a>
                            @if(in_array($schedule->notification->status, ['sent', 'sending'], true))
                                <div class="small text-warning mt-1">This notification has left the journal — it cannot be recalled by pausing or editing.</div>
                            @endif
                        @elseif($schedule->notify_push)
                            @if($schedule->status === \App\Models\IssueSchedule::STATUS_DRAFT)
                                <div class="small text-muted">Will be queued when the announcement is scheduled.</div>
                            @else
                                <div class="small text-danger">No notification was queued: the “{{ $schedule->pushCategory() }}” push category is not marked eligible. Mark it eligible on the push screens and resume/re-publish to queue it.</div>
                            @endif
                        @else
                            <div class="small text-muted">Not requested for this schedule.</div>
                        @endif
                    </div>

                    <div class="border rounded-3 p-3">
                        <div class="fw-semibold small">What scheduling never does</div>
                        <div class="small text-muted">
                            The issue stays with editorial: announcing it never sets the issue (or any article) to
                            published. The issue above keeps its editors' status; publication happens only through the
                            editorial workflow.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card panel-card mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1"><i class="bi bi-clock-history me-1"></i>Audit trail</h5>
            <p class="text-muted mb-0">Every plan and announcement action for this schedule, most recent first.</p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">When</th>
                            <th>User</th>
                            <th>Event</th>
                            <th class="pe-4">Recorded</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trail as $log)
                            <tr>
                                <td class="px-4 small text-muted">{{ $log->created_at?->format('d M Y H:i') }}</td>
                                <td class="small">{{ $log->user?->name ?? '—' }}</td>
                                <td class="small font-monospace">{{ $log->event }}</td>
                                <td class="small text-muted"><code style="font-size:.7rem;">{{ json_encode($log->new_values) }}</code></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Nothing recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mb-5">
        <a href="{{ route('admin.schedules.index') }}" class="btn btn-outline-dark">Back to schedules</a>
    </div>
</div>
@endsection
