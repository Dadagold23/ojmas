@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-warning px-3 py-2 mb-2">Revision</span>
            <h3 class="fw-bold mb-1">Compare versions</h3>
            <p class="text-muted mb-0">{{ $submission->title }}</p>
        </div>
        <a href="{{ route('author.revisions.show', $submission) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to the revision record
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3">What you submitted and what was revised</h6>

            @if($versions->isEmpty())
                <p class="text-muted small mb-0">No versions are on record yet.</p>
            @else
                <div class="row g-3">
                    @foreach($versions as $index => $version)
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 {{ $index === 0 ? 'border-primary' : '' }}">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="small">v{{ $version->version_number ?? $version->getKey() }}</strong>
                                    @if($index === 0)<span class="badge text-bg-light">original</span>@endif
                                </div>
                                <dl class="row small mb-2">
                                    <dt class="col-5 text-muted">Type</dt>
                                    <dd class="col-7">{{ $version->origin ?? '—' }}</dd>

                                    <dt class="col-5 text-muted">Created</dt>
                                    <dd class="col-7">{{ $version->created_at?->format('d M Y H:i') }}</dd>
                                </dl>
                                @if($version->document)
                                    <a href="{{ route('workspace.show', $submission) }}" class="btn btn-sm btn-outline-primary">
                                        Inspect in the workspace
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="alert alert-info rounded-4 small">
        <i class="bi bi-info-circle me-1"></i>
        Full side-by-side diffing, tracked changes and version restore live in the manuscript workspace.
        Anything a reviewer marked there was written against the version they were sent.
    </div>
</div>
@endsection
