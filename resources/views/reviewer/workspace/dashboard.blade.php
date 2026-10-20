@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <span class="badge text-bg-light text-success px-3 py-2 mb-2">Reviewer workspace</span>
            <h3 class="fw-bold mb-1">My review assignments</h3>
            <p class="text-muted mb-0">
                You see only the manuscripts you have been invited to review, in blinded form.
            </p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['Invitations awaiting your response', $stats['invitations'], 'warning'],
            ['Reviews in progress', $stats['in_progress'], 'primary'],
            ['Reviews completed', $stats['completed'], 'success'],
            ['Overdue', $stats['overdue'], 'danger'],
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

    @if($invitations->isNotEmpty())
        <div class="card border-warning shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-envelope-exclamation me-2 text-warning"></i>Invitations awaiting your response</h5>
                @foreach($invitations as $invitation)
                    <div class="border rounded-3 p-3 mb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <div class="fw-semibold">{{ $invitation->submission?->title ?? 'Withdrawn submission' }}</div>
                            <div class="text-muted small">
                                Invited {{ $invitation->invited_at?->diffForHumans() }}
                                @if($invitation->due_at)
                                    · Due {{ $invitation->due_at->format('d M Y') }}
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('reviewer.workspace.invitations.respond', $invitation) }}" class="d-flex gap-2">
                            @csrf
                            <button name="response" value="accepted" class="btn btn-sm btn-success">Accept</button>
                            <button name="response" value="declined" class="btn btn-sm btn-outline-secondary">Decline</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-clipboard-check me-2 text-primary"></i>Assignments</h5>

            @if($assignments->isEmpty())
                <p class="text-muted mb-0">You have no review assignments yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Manuscript</th>
                                <th>Reference</th>
                                <th>Due</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assignments as $assignment)
                                <tr>
                                    <td>
                                        <div class="fw-semibold small">{{ $assignment->submission?->title ?? 'Unavailable' }}</div>
                                        @if($assignment->blind_code)
                                            <code class="small text-muted">{{ $assignment->blind_code }}</code>
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ $assignment->submission?->subject_area ?? '—' }}</td>
                                    <td class="small">
                                        {{ $assignment->due_date?->format('d M Y') ?? '—' }}
                                        @if($assignment->isOverdue())
                                            <span class="badge text-bg-danger ms-1">Overdue</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($assignment->review?->isSubmitted())
                                            <span class="badge text-bg-success">Submitted</span>
                                        @elseif($assignment->review?->isDraft())
                                            <span class="badge text-bg-info">Draft saved</span>
                                        @elseif($assignment->status === 'declined')
                                            <span class="badge text-bg-secondary">Declined</span>
                                        @else
                                            <span class="badge text-bg-warning">{{ ucfirst($assignment->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('reviewer.workspace.show', $assignment) }}" class="btn btn-sm btn-outline-primary">
                                            Open
                                        </a>
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