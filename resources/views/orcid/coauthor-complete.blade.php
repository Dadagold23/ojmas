@extends('layouts.app')

@section('page_title', 'ORCID iD verified')

@section('content')
<div class="container py-5" style="max-width:600px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="d-flex justify-content-center mb-3">
                <x-orcid-icon :size="48" />
            </div>
            <h1 class="h4 fw-bold">ORCID iD verified</h1>

            @if(filled($orcid))
                <p class="mb-2">
                    <span class="badge rounded-pill text-bg-success">
                        <i class="bi bi-patch-check-fill me-1"></i>Verified via ORCID
                    </span>
                </p>
                <p class="fs-5 mb-3">
                    <a href="https://orcid.org/{{ $orcid }}" target="_blank" rel="noopener noreferrer">{{ $orcid }}</a>
                </p>
            @endif

            <p class="text-muted mb-4">
                @if(filled($name))
                    Thank you, {{ $name }}. Your verified ORCID iD is now attached to your author record.
                @else
                    Your verified ORCID iD is now attached to your author record.
                @endif
                You can close this window.
            </p>

            <a href="{{ route('home') }}" class="btn btn-outline-secondary">Return to the journal</a>
        </div>
    </div>
</div>
@endsection
