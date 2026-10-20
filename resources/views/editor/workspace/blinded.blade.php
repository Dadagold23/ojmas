@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-dark px-3 py-2 mb-2">Blinded review copy</span>
            <h3 class="fw-bold mb-1">{{ $submission->submission_no }}</h3>
            <p class="text-muted mb-0">{{ $submission->title }}</p>
        </div>
        <a href="{{ route('editor.workspace.show', $submission) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <div class="alert alert-warning rounded-4 small mb-4">
        <i class="bi bi-shield-exclamation me-1"></i>
        Review this manifest <strong>before</strong> the copy is issued to reviewers. Anonymisation keeps a
        record of what it removed and asks a human to look at anything it was unsure about — that includes you.
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-check2-circle me-2 text-success"></i>What was removed</h6>

                    @if($result['transformations'] === [])
                        <p class="text-muted small mb-0">
                            Nothing needed removing — the stored file carries no detectable author identity.
                            That is unusual; check that the right source file exists.
                        </p>
                    @else
                        <ul class="small mb-0 ps-3">
                            @foreach($result['transformations'] as $transformation)
                                <li class="mb-1">{{ $transformation }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 {{ $result['residual_risks'] ? 'border-danger' : '' }}">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-exclamation-triangle me-2 {{ $result['residual_risks'] ? 'text-danger' : 'text-success' }}"></i>
                        Residual risks needing your judgement
                    </h6>

                    @if($result['residual_risks'] === [])
                        <p class="text-success small mb-0">
                            <i class="bi bi-shield-check me-1"></i>
                            None reported. Service names, metadata and body text were all clear.
                        </p>
                    @else
                        <ul class="small text-danger mb-0 ps-3">
                            @foreach($result['residual_risks'] as $risk)
                                <li class="mb-1">{{ $risk }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold">Download and verify: {{ $result['filename'] }}</div>
                <div class="text-muted small">
                    Blinding code {{ $result['code'] }} · stored on the private disk; reviewers reach it only
                    through their assignment.
                </div>
            </div>
            <a href="{{ route('editor.workspace.show', $submission) }}" class="btn btn-outline-primary btn-sm">
                Return to the manuscript
            </a>
        </div>
    </div>
</div>
@endsection
