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

    .cal-cell { height: 108px; overflow: hidden; }
    .cal-table { table-layout: fixed; }
    .cal-day-head { font-size: .75rem; font-weight: 700; color:#475569; }
    .cal-chip { font-size: .62rem; border-radius: 999px; padding: .1rem .4rem; display: inline-block;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; margin-bottom: .15rem; }
    .cal-announce { background:#dbeafe; color:#1e40af; }
    .cal-publication { background:#d1fae5; color:#065f46; }
    .cal-cfp_open { background:#fef3c7; color:#92400e; }
    .cal-deadline { background:#fee2e2; color:#991b1b; }
    .cal-push { background:#e9d5ff; color:#6b21a8; }
    .cal-campaign_start { background:#cffafe; color:#155e75; }
    .cal-campaign_end { background:#f1f5f9; color:#475569; }
</style>

<div class="container py-4">
    <div class="card sched-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-2"><i class="bi bi-calendar2-week me-2"></i>Issue &amp; announcement scheduling</h2>
                    <p class="mb-0 text-white-50">
                        Plan upcoming volumes and issues and prepare their announcements in advance.
                        Scheduling prepares announcements only — it never publishes an issue or its articles.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    @can('scheduling.manage')
                        <a href="{{ route('admin.schedules.create') }}" class="btn btn-warning fw-semibold">
                            <i class="bi bi-plus-lg me-1"></i>New schedule
                        </a>
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

    {{-- Month calendar --}}
    <div class="card panel-card mb-4" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1"><i class="bi bi-calendar3 me-1"></i>{{ $calendar['label'] }}</h5>
                    <p class="text-muted mb-0">Recorded dates only — announcements, planned publications, call dates, queued push and campaign windows.</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('admin.schedules.index', ['y' => $calendar['prev']['y'], 'm' => $calendar['prev']['m']]) }}">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('admin.schedules.index', ['y' => $calendar['month']->year, 'm' => $calendar['month']->month]) }}">Today</a>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('admin.schedules.index', ['y' => $calendar['next']['y'], 'm' => $calendar['next']['m']]) }}">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 cal-table">
                    <thead>
                        <tr>
                            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                                <th class="cal-day-head text-center py-2">{{ $dayName }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($calendar['weeks'] as $week)
                            <tr>
                                @foreach($week as $cell)
                                    @php $chipLimit = 3; @endphp
                                    <td class="cal-cell @if(!$cell['inMonth']) text-muted bg-body-tertiary @endif align-self-top"
                                        style="{{ $cell['today'] ? 'outline:2px solid #0d6efd; outline-offset:-2px;' : '' }}">
                                        <div class="small fw-semibold mb-1">{{ $cell['date']->day }}</div>
                                        @foreach($cell['events'] as $chip)
                                            @if($loop->iteration <= $chipLimit)
                                                <span class="cal-chip cal-{{ $chip['kind'] }}" title="{{ $chip['sub'] }}">{{ $chip['label'] }}</span>
                                            @endif
                                        @endforeach
                                        @if(count($cell['events']) > $chipLimit)
                                            <span class="cal-chip cal-campaign_end">+{{ count($cell['events']) - $chipLimit }} more</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Schedule listing --}}
    <div class="card panel-card mb-4" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Issue schedules</h5>
            <p class="text-muted mb-3">Scheduled first, then drafts, then paused and cancelled plans — records are kept, never deleted.</p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Volume / issue</th>
                            <th>Announcement</th>
                            <th>Announce at</th>
                            <th>Planned publication</th>
                            <th>Push</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedules as $schedule)
                            <tr>
                                <td class="px-4">
                                    <div class="fw-semibold small">
                                        Volume {{ $schedule->volume?->volume_no ?? '—' }} · Issue {{ $schedule->issue?->issue_no ?? '—' }}
                                    </div>
                                    <div class="text-muted" style="font-size:.75rem;">
                                        {{ $schedule->volume?->year }} · {{ \App\Models\IssueSchedule::ANNOUNCEMENT_TYPES[$schedule->announcement_type] ?? $schedule->announcement_type }}
                                    </div>
                                </td>
                                <td class="small">
                                    <div>{{ \Illuminate\Support\Str::limit($schedule->title, 46) }}</div>
                                    @if($schedule->campaign)
                                        <div class="text-muted" style="font-size:.72rem;">
                                            <i class="bi bi-megaphone me-1"></i>window {{ $schedule->campaign->starts_at?->format('d M Y H:i') }} → {{ $schedule->campaign->ends_at?->format('d M Y H:i') ?? '—' }}
                                        </div>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $schedule->announce_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="small text-muted">{{ $schedule->planned_publication_date?->format('d M Y') ?? '—' }}</td>
                                <td class="small text-muted">
                                    @if($schedule->notify_push)
                                        @if($schedule->notification)
                                            queued ({{ $schedule->notification->status }})
                                        @else
                                            <span class="text-danger">category not eligible</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="status-chip status-{{ $schedule->status }}">{{ \App\Models\IssueSchedule::STATUSES[$schedule->status] ?? $schedule->status }}</span>
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="{{ route('admin.schedules.show', $schedule) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5">No issue schedules yet — plan the next volume/issue from “New schedule”.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-0 px-4 pb-4">
            {{ $schedules->links() }}
        </div>
    </div>
</div>
@endsection
