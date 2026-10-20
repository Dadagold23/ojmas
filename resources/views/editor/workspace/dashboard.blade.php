@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <span class="badge text-bg-light text-primary px-3 py-2 mb-2">Editorial workspace</span>
            <h3 class="fw-bold mb-1">My assigned manuscripts</h3>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['Assigned to me', $stats['assigned'], 'primary'],
            ['Awaiting reviewer reports', $stats['awaiting_reports'], 'warning'],
            ['Reports in, decision pending', $stats['awaiting_decision'], 'info'],
            ['With the author', $stats['in_revision'], 'secondary'],
        ] as [$label, $value, $colour])
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-3 fw-bold text-{{ $colour }}">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            @if($submissions->isEmpty())
                <p class="text-muted mb-0">No manuscripts are currently assigned to you.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Manuscript</th>
                                <th>Stage</th>
                                <th>Reviewers</th>
                                <th>Reports in</th>
                                <th>Due</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($submissions as $submission)
                                <tr>
                                    <td>
                                        <div class="fw-semibold small">{{ $submission->title }}</div>
                                        <code class="small text-muted">{{ $submission->submission_no }}</code>
                                    </td>
                                    <td class="small">{{ $submission->currentStageName() }}</td>
                                    <td class="small">{{ $submission->reviewerAssignments->count() }}</td>
                                    <td class="small">
                                        {{ $submission->reviewerAssignments->where('status', 'completed')->count() }}
                                        @if($submission->isOverdue())
                                            <span class="badge text-bg-danger ms-1">Overdue</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ $submission->decision_due_at?->format('d M Y') ?? '—' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('editor.workspace.show', $submission) }}" class="btn btn-sm btn-outline-primary">Open</a>
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
@endsection
