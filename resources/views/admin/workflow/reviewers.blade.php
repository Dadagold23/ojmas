@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-danger px-3 py-2 mb-2">Workflow management</span>
            <h3 class="fw-bold mb-1">Reviewer tracking</h3>
            <p class="text-muted mb-0">Invitations, responses, workload — one place per reviewer.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.workflow.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Pipeline
            </a>
            <a href="{{ route('admin.workflow.report') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger rounded-4 mb-4">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Overdue first: that is the list an Managing Editor acts on --}}
    @if($overdue->isNotEmpty())
        <div class="card border-danger shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold text-danger mb-3"><i class="bi bi-hourglass-split me-2"></i>Overdue reviewer responses — {{ $overdue->count() }}</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Reviewer</th><th>Manuscript</th><th>Invited</th><th>Was due</th><th>Reminders</th><th></th></tr></thead>
                        <tbody>
                            @foreach($overdue as $invitation)
                                <tr>
                                    <td class="small fw-semibold">{{ $invitation->reviewer?->name }}</td>
                                    <td class="small">{{ Str::limit($invitation->submission?->title, 50) }}</td>
                                    <td class="small text-muted">{{ $invitation->invited_at?->format('d M Y') }}</td>
                                    <td class="small text-danger">{{ $invitation->due_at->diffForHumans() }}</td>
                                    <td class="small">{{ $invitation->reminder_count }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.workflow.invitations.remind', $invitation) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-bell me-1"></i>Record reminder
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-warning">Pending responses — {{ $pending->count() }}</h6>
                    @foreach($pending as $invitation)
                        <div class="d-flex justify-content-between align-items-center border rounded-3 px-3 py-2 mb-2">
                            <div class="small">
                                <div class="fw-semibold">{{ $invitation->reviewer?->name }}</div>
                                <div class="text-muted">{{ Str::limit($invitation->submission?->title, 45) }}</div>
                            </div>
                            <form method="POST" action="{{ route('admin.workflow.invitations.remind', $invitation) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary" title="Record a reminder">
                                    <i class="bi bi-bell"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                    @if($pending->isEmpty())<p class="text-muted small mb-0">None pending.</p>@endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-secondary">Declined — {{ $declined->count() }}</h6>
                    @foreach($declined as $invitation)
                        <div class="border rounded-3 px-3 py-2 mb-2 small">
                            <span class="fw-semibold">{{ $invitation->reviewer?->name }}</span>
                            <span class="text-muted">· {{ Str::limit($invitation->submission?->title, 45) }}</span>
                            @if($invitation->response_note)
                                <div class="text-muted">“{{ Str::limit($invitation->response_note, 90) }}”</div>
                            @endif
                        </div>
                    @endforeach
                    @if($declined->isEmpty())<p class="text-muted small mb-0">No declines recorded.</p>@endif
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-people me-2 text-primary"></i>Reviewer workload</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Reviewer</th><th>Active assignments</th><th>Completed all-time</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($workload as $row)
                            <tr>
                                <td class="small fw-semibold">{{ $row->reviewer->name }}</td>
                                <td class="small">
                                    <span class="badge {{ $row->active > 3 ? 'text-bg-warning' : 'text-bg-light' }}">{{ $row->active }}</span>
                                </td>
                                <td class="small text-muted">{{ $row->completed }}</td>
                                <td class="text-end small text-muted">
                                    @if($row->active === 0)
                                        <span class="text-success">Available</span>
                                    @else
                                        {{ $row->active }} in progress
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
