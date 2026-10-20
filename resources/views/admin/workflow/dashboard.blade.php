@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-danger px-3 py-2 mb-2">Workflow management</span>
            <h3 class="fw-bold mb-1">Editorial pipeline</h3>
            <p class="text-muted mb-0">What is moving, what is stuck, and who has been asked for what.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.workflow.reviewers') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-people me-1"></i>Reviewer tracking
            </a>
            <a href="{{ route('admin.workflow.report') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export report (CSV)
            </a>
        </div>
    </div>

    @if($errors->any() || session('warning'))
        <div class="alert alert-warning rounded-4 mb-4">{{ session('warning') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach([
            ['Awaiting an editorial decision', $overview['awaiting_decision'], 'info', 'bi-gavel'],
            ['Awaiting reviewer assignment', $overview['awaiting_assignment'], 'warning', 'bi-person-plus'],
            ['With the author', $overview['in_revision'], 'secondary', 'bi-pencil'],
            ['In peer review', $overview['in_peer_review'], 'primary', 'bi-people'],
        ] as [$label, $value, $colour, $icon])
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fs-3 fw-bold text-{{ $colour }}">{{ $value }}</div>
                        </div>
                        <i class="bi {{ $icon }} fs-3 text-{{ $colour }} opacity-50"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
                <div class="card-body">
                    <div class="text-muted small">Overdue manuscripts</div>
                    <div class="fs-2 fw-bold text-danger">{{ $overview['overdue'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-muted small">Reviewer responses pending</div>
                    <div class="fs-2 fw-bold text-warning">{{ $overview['pending_reviewer_responses'] }}</div>
                    <div class="text-danger small">{{ $overview['overdue_reviewer_responses'] }} overdue</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small">Accepted, awaiting production</div>
                    <div class="fs-2 fw-bold text-success">{{ $overview['accepted_awaiting_production'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-secondary">
                <div class="card-body">
                    <div class="text-muted small">Published to date</div>
                    <div class="fs-2 fw-bold">{{ $overview['published'] }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($overdue->isNotEmpty())
        <div class="card border-danger shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold text-danger mb-3"><i class="bi bi-hourglass-split me-2"></i>Delayed manuscripts — past their stage deadline</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Manuscript</th><th>Stage</th><th>Editor</th><th>Was due</th><th></th></tr></thead>
                        <tbody>
                            @foreach($overdue as $submission)
                                <tr>
                                    <td class="small">
                                        <div class="fw-semibold">{{ Str::limit($submission->title, 60) }}</div>
                                        <code class="text-muted">{{ $submission->submission_no }}</code>
                                    </td>
                                    <td class="small">{{ $submission->currentStageName() }}</td>
                                    <td class="small text-muted">{{ $submission->assignedEditor?->name ?? 'Unassigned' }}</td>
                                    <td class="small text-danger fw-semibold">{{ $submission->decision_due_at->diffForHumans() }}</td>
                                    <td class="text-end">
                                        @can('viewAny', $submission)
                                            <a href="{{ route('editor.workspace.show', $submission) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-gavel me-2 text-info"></i>Awaiting a decision</h6>
                    @if($awaitingDecision->isEmpty())
                        <p class="text-muted small mb-0">Nothing is waiting on an editor right now.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Manuscript</th><th>Reports</th><th></th></tr></thead>
                                <tbody>
                                    @foreach($awaitingDecision as $submission)
                                        <tr>
                                            <td class="small">
                                                <div class="fw-semibold">{{ Str::limit($submission->title, 55) }}</div>
                                                <code class="text-muted">{{ $submission->submission_no }}</code>
                                            </td>
                                            <td class="small">
                                                {{ $submission->reviewerAssignments->where('status', 'completed')->count() }}
                                                / {{ $submission->reviewerAssignments->count() }}
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('editor.workspace.show', $submission) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-plus me-2 text-warning"></i>Awaiting reviewer assignment</h6>
                    @if($awaitingAssignment->isEmpty())
                        <p class="text-muted small mb-0">Every active manuscript has at least one reviewer.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Manuscript</th><th>Editor</th><th></th></tr></thead>
                                <tbody>
                                    @foreach($awaitingAssignment as $submission)
                                        <tr>
                                            <td class="small">
                                                <div class="fw-semibold">{{ Str::limit($submission->title, 55) }}</div>
                                                <code class="text-muted">{{ $submission->submission_no }}</code>
                                            </td>
                                            <td class="small text-muted">{{ $submission->assignedEditor?->name ?? 'Unassigned' }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('editor.workspace.show', $submission) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart me-2 text-primary"></i>Manuscripts by stage</h6>
            <div class="row g-2">
                @forelse($distribution as $stage => $count)
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border rounded-3 px-3 py-2">
                            <span class="small">{{ $stage }}</span>
                            <span class="badge text-bg-light">{{ $count }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No stages configured yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
