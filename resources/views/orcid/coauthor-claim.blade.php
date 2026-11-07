@extends('layouts.app')

@section('page_title', 'Verify your ORCID iD')

@section('content')
<div class="container py-5" style="max-width:640px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex align-items-center gap-3 mb-3">
                <x-orcid-icon :size="40" />
                <div>
                    <h1 class="h4 fw-bold mb-0">Verify your ORCID iD</h1>
                    <div class="small text-muted">Co-author verification for an OJMAS submission</div>
                </div>
            </div>

            @if(session('orcid_error'))
                <div class="alert alert-danger small" role="alert">
                    <strong>Verification did not complete.</strong><br>
                    {{ session('orcid_error') }}
                </div>
            @endif

            @php($author = $claim->articleAuthor)
            @php($submission = $author?->submission)

            <p class="mb-2">
                You are verifying the ORCID iD for
                <strong>{{ $author?->full_name }}</strong>@if(filled($author?->email))
                    ({{ $author->email }})@endif
                on the submission
                <em>{{ $submission?->title ?? 'recorded manuscript' }}</em>.
            </p>

            @if($author?->hasVerifiedOrcid())
                <div class="alert alert-success d-flex align-items-center gap-2">
                    <i class="bi bi-patch-check-fill"></i>
                    <span>This co-author already has a verified ORCID iD: {{ $author->orcid }}.</span>
                </div>
            @elseif(! $enabled)
                <div class="alert alert-warning">
                    ORCID connection is not enabled for this journal yet. Please contact the editorial office.
                </div>
            @else
                @if($signedInAsWrongAccount)
                    <div class="alert alert-warning small">
                        You are signed in as <strong>{{ auth()->user()->email }}</strong>, but this invitation belongs to
                        <strong>{{ $author?->email }}</strong>. To verify this co-author, sign out and open the link again,
                        or sign in with the invited e-mail address.
                    </div>
                @endif

                <p class="small text-muted">
                    This personal link can be used once. Authenticating proves that you control this ORCID iD;
                    OJMAS never receives or asks for your ORCID password.
                </p>

                <form method="POST" action="{{ route('orcid.coauthor.start', ['token' => $token]) }}">
                    @csrf
                    <button type="submit" class="btn orcid-btn fw-semibold d-inline-flex align-items-center gap-2">
                        <x-orcid-icon :size="18" />
                        Authenticate with ORCID
                    </button>
                </form>

                <div class="small text-muted mt-3">
                    Don't have an ORCID iD?
                    <a href="https://orcid.org/register" target="_blank" rel="noopener noreferrer">Register here</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@once
    <style>
        .orcid-btn { background: #A6CE39; color: #1f2a0b; border: 1px solid #95bb2f; }
        .orcid-btn:hover, .orcid-btn:focus { background: #95bb2f; color: #111a05; }
    </style>
@endonce
