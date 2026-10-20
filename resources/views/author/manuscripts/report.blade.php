@extends('layouts.app')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-primary px-3 py-2 mb-2">Full validation report</span>
            <h3 class="fw-bold mb-1">{{ $submission->submission_no }}</h3>
            <p class="text-muted mb-0">{{ $submission->title }}</p>
        </div>
        <a href="{{ route('author.manuscripts.show', $submission) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted small">Rule set</div><div class="fw-semibold">{{ data_get($validation->report_json, 'profile.name') }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Rule version</div><div class="fw-semibold">{{ $validation->profile_version }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Checked</div><div class="fw-semibold">{{ $validation->created_at?->format('d M Y, H:i') }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Status</div>
                    @if($validation->isApproved())
                        <span class="badge text-bg-success">Approved</span>
                    @elseif($validation->blocksProgression())
                        <span class="badge text-bg-danger">Action required</span>
                    @else
                        <span class="badge text-bg-secondary">Awaiting your approval</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($errors->isNotEmpty())
        <div class="card border-danger shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold text-danger mb-3"><i class="bi bi-exclamation-octagon me-2"></i>Issues you must resolve ({{ $errors->count() }})</h5>
                <p class="text-muted small">These are requirements the journal cannot process around. Your wording has not been changed &mdash; only you can decide how to fix them.</p>
                @foreach($errors as $issue)
                    <div class="border rounded-3 p-3 mb-2">
                        <div class="fw-semibold small">{{ $issue->message }}</div>
                        @if($issue->suggestion)
                            <div class="text-muted small mt-1"><i class="bi bi-arrow-return-right me-1"></i>{{ $issue->suggestion }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($warnings->isNotEmpty())
        <div class="card border-warning shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-chat-left-text me-2 text-warning"></i>Please check these ({{ $warnings->count() }})</h5>
                <p class="text-muted small">
                    The engine could not decide whether these are wrong, so nothing has been changed. It will never
                    invent a missing author, title, year or page range to close a gap.
                </p>
                @foreach($warnings as $issue)
                    <div class="border rounded-3 p-3 mb-2">
                        <div class="small">{{ $issue->message }}</div>
                        @if($issue->suggestion)
                            <div class="text-muted small mt-1"><i class="bi bi-arrow-return-right me-1"></i>{{ $issue->suggestion }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($changes->isNotEmpty())
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-check2-square me-2 text-success"></i>Corrected automatically ({{ $changes->count() }})</h5>
                <p class="text-muted small">These are objective formatting requirements. Open the formatted document and confirm they look right.</p>
                <ul class="list-group list-group-flush">
                    @foreach($changes as $issue)
                        <li class="list-group-item px-0 bg-transparent">
                            <div class="small">{{ $issue->message }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-shield-check me-2 text-primary"></i>Preserved content</h6>
            @php $preserved = $validation->issues->where('category', 'conversion'); @endphp
            @if($preserved->isEmpty())
                <p class="text-muted small mb-0">Every element in your manuscript was recognised and carried through unchanged.</p>
            @else
                <ul class="small text-muted mb-0" style="padding-left:1.1rem;line-height:1.9;">
                    @foreach($preserved as $issue)
                        <li>{{ $issue->message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection