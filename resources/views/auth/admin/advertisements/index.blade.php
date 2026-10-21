@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .ads-hero { border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(234,88,12,.92), rgba(15,23,42,.92));
        color:#fff; box-shadow: 0 20px 50px rgba(15,23,42,.16); }

    .status-chip { border-radius: 999px; padding: .3rem .7rem; font-size: .75rem; font-weight: 600; }
    .status-published { background: #d1fae5; color: #065f46; }
    .status-scheduled { background: #dbeafe; color: #1e40af; }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-draft { background: #e2e8f0; color: #334155; }
    .status-paused { background: #fce7f3; color: #9d174d; }
    .status-expired { background: #fee2e2; color: #991b1b; }
    .status-archived { background: #f1f5f9; color: #64748b; }
</style>

<div class="container py-4">
    <div class="card ads-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-2"><i class="bi bi-megaphone me-2"></i>Advertisements &amp; Announcements</h2>
                    <p class="mb-0 text-white-50">
                        Revenue advertisements and official journal announcements.
                        Campaigns appear only inside their publication window and only on their configured placements.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0 d-flex flex-column gap-2 align-items-lg-end">
                    <a href="{{ route('admin.advertisements.create') }}" class="btn btn-warning fw-semibold">
                        <i class="bi bi-plus-lg me-1"></i>New campaign
                    </a>
                    @can('advertising.configure')
                        <a href="{{ route('admin.advertisements.placements') }}" class="btn btn-outline-light">
                            <i class="bi bi-sliders me-1"></i>Placement configuration
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

    {{-- Status overview --}}
    <div class="row g-3 mb-4">
        @foreach(\App\Models\AdCampaign::STATUSES as $statusKey => $statusLabel)
            <div class="col-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.advertisements.index', ['status' => $statusKey]) }}"
                   class="text-decoration-none">
                    <div class="card panel-card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">{{ $statusLabel }}</div>
                                <div class="fw-bold fs-4">{{ $statusCounts[$statusKey] ?? 0 }}</div>
                            </div>
                            <span class="status-chip status-{{ $statusKey }}">{{ $statusLabel }}</span>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="card panel-card mb-4" data-aos="fade-up">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('admin.advertisements.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Search</label>
                    <input type="text" name="q" class="form-control" value="{{ $search }}"
                           placeholder="Title, advertiser, description...">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach(\App\Models\AdCampaign::STATUSES as $statusKey => $statusLabel)
                            <option value="{{ $statusKey }}" @selected($status === $statusKey)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All types</option>
                        @foreach(\App\Models\AdCampaign::TYPES as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}" @selected($type === $typeKey)>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Placement</label>
                    <select name="placement" class="form-select">
                        <option value="">All placements</option>
                        @foreach($placements as $placement)
                            <option value="{{ $placement->id }}" @selected($placementId == $placement->id)>{{ $placement->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary w-100">Filter</button>
                    <a href="{{ route('admin.advertisements.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Campaign listing --}}
    <div class="card panel-card mb-4" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Campaigns</h5>
            <p class="text-muted mb-3">Publish, pause, resume and archive campaigns. Records are archived, never deleted.</p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Campaign</th>
                            <th>Type</th>
                            <th>Placement</th>
                            <th>Window</th>
                            <th>Status</th>
                            <th>Performance</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($listings as $campaign)
                            <tr>
                                <td class="px-4">
                                    <div class="fw-semibold small">{{ \Illuminate\Support\Str::limit($campaign->title, 50) }}</div>
                                    <div class="text-muted" style="font-size:.75rem;">
                                        {{ $campaign->advertiser_name ?? 'Internal' }} · priority {{ $campaign->priority }}
                                    </div>
                                </td>
                                <td class="small">{{ \App\Models\AdCampaign::TYPES[$campaign->type] ?? $campaign->type }}</td>
                                <td class="small text-muted">{{ $campaign->placement?->label ?? '—' }}</td>
                                <td class="small text-muted">
                                    {{ $campaign->starts_at?->format('d M Y H:i') ?? '—' }}
                                    →
                                    {{ $campaign->ends_at?->format('d M Y H:i') ?? '—' }}
                                </td>
                                <td>
                                    <span class="status-chip status-{{ $campaign->status }}">{{ \App\Models\AdCampaign::STATUSES[$campaign->status] ?? $campaign->status }}</span>
                                    @if($campaign->isPubliclyVisible())
                                        <span class="badge bg-success-subtle text-success-emphasis" style="font-size:.65rem;">live</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $campaign->events()->where('event','impression')->count() }} imps · {{ $campaign->events()->where('event','click')->count() }} clicks</td>
                                <td class="pe-4 text-end">
                                    <a href="{{ route('admin.advertisements.show', $campaign) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5">No campaigns match this filter yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-0 px-4 pb-4">
            {{ $listings->links() }}
        </div>
    </div>

    {{-- Scheduling calendar strip --}}
    <div class="card panel-card" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1"><i class="bi bi-calendar-week me-1"></i>Scheduled campaign calendar</h5>
            <p class="text-muted mb-3">Campaign windows on record, soonest first where scheduled.</p>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="row g-3">
                @forelse($calendarWindow->sortBy([['starts_at', 'asc']]) as $campaign)
                    <div class="col-md-6 col-xl-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-semibold small mb-1">{{ \Illuminate\Support\Str::limit($campaign->title, 60) }}</div>
                            <div class="small text-muted">
                                <i class="bi bi-play-btn me-1"></i>{{ $campaign->starts_at?->format('d M Y H:i') ?? 'now' }}
                                <i class="bi bi-stop-btn mx-1"></i>{{ $campaign->ends_at?->format('d M Y H:i') ?? 'until paused' }}
                            </div>
                            <div class="small text-muted">{{ $campaign->placement?->label }}</div>
                            <span class="status-chip status-{{ $campaign->status }} mt-2 d-inline-block">{{ \App\Models\AdCampaign::STATUSES[$campaign->status] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-muted">No campaign windows recorded yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
