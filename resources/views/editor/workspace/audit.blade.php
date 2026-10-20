@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-dark px-3 py-2 mb-2">Audit history</span>
            <h3 class="fw-bold mb-1">{{ $submission->submission_no }}</h3>
            <p class="text-muted mb-0">Append-only record. Nothing here can be edited or deleted.</p>
        </div>
        <a href="{{ route('editor.workspace.show', $submission) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Workflow log</h6>
                    @foreach($statusHistory as $entry)
                        <div class="border-bottom pb-2 mb-2 small">
                            <div class="fw-semibold">{{ $entry->old_status }} → {{ $entry->new_status }}</div>
                            <div class="text-muted">{{ $entry->changedBy?->name ?? 'System' }} · {{ $entry->created_at?->format('d M Y H:i') }}</div>
                            @if($entry->note)<div class="text-muted">{{ $entry->note }}</div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Stage and decision events</h6>
                    @forelse($history as $event)
                        <div class="border-bottom pb-2 mb-2 small">
                            <div class="fw-semibold">{{ str_replace('_', ' ', $event->event) }}</div>
                            <div class="text-muted">
                                {{ $event->actor?->name ?? 'System' }} · {{ $event->created_at?->format('d M Y H:i') }}
                                @if($event->from_stage || $event->to_stage)
                                    · {{ $event->from_stage ?? '∅' }} → {{ $event->to_stage ?? '∅' }}
                                @endif
                            </div>
                            @if($event->reason)<div class="text-muted">Reason: {{ $event->reason }}</div>@endif
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No events yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Document access</h6>
                    @forelse($accessLogs as $log)
                        <div class="border-bottom pb-2 mb-2 small">
                            <span class="badge text-bg-light me-1">{{ ucfirst($log->action ?? 'view') }}</span>
                            {{ $log->user?->name ?? 'Unknown' }}
                            <span class="text-muted">· {{ $log->created_at?->format('d M Y H:i') }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No document access recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
