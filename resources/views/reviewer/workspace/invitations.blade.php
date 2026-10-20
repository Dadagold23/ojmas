@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-success px-3 py-2 mb-2">Reviewer workspace</span>
            <h3 class="fw-bold mb-1">Invitations</h3>
            <p class="text-muted mb-0">Every invitation you have received, and how you responded.</p>
        </div>
        <a href="{{ route('reviewer.workspace.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger rounded-4 mb-4">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            @if($invitations->isEmpty())
                <p class="text-muted mb-0">No invitations yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Manuscript</th>
                                <th>Invited</th>
                                <th>Due</th>
                                <th>Response</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invitations as $invitation)
                                <tr>
                                    <td class="small fw-semibold">{{ $invitation->submission?->title ?? 'Unavailable' }}</td>
                                    <td class="small text-muted">
                                        {{ $invitation->invited_at?->format('d M Y') ?? '—' }}
                                        @if($invitation->inviter)
                                            · by {{ $invitation->inviter->name }}
                                        @endif
                                    </td>
                                    <td class="small">
                                        {{ $invitation->due_at?->format('d M Y') ?? '—' }}
                                        @if($invitation->isOverdue())
                                            <span class="badge text-bg-danger ms-1">Overdue</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge text-bg-{{ $invitation->response === 'accepted' ? 'success' : ($invitation->response === 'declined' ? 'secondary' : 'warning') }}">
                                            {{ ucfirst($invitation->response) }}
                                        </span>
                                    </td>
                                    <td class="text-end small text-muted">
                                        @if($invitation->responded_at)
                                            {{ $invitation->responded_at->format('d M Y') }}
                                        @elseif($invitation->submission)
                                            <form method="POST" action="{{ route('reviewer.workspace.invitations.respond', $invitation) }}" class="d-inline-flex gap-1">
                                                @csrf
                                                <button name="response" value="accepted" class="btn btn-sm btn-outline-success">Accept</button>
                                                <button name="response" value="declined" class="btn btn-sm btn-outline-secondary">Decline</button>
                                            </form>
                                        @endif
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
