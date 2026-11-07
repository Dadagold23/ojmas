@extends('layouts.app')

@section('page_title', 'Verification link unavailable')

@section('content')
<div class="container py-5" style="max-width:600px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="d-flex justify-content-center mb-3">
                <x-orcid-icon :size="40" />
            </div>
            <h1 class="h4 fw-bold">This verification link is no longer valid</h1>
            <p class="text-muted mb-4">
                The link may have expired, already been used, or been replaced by a newer invitation.
                Ask the corresponding author to issue a fresh verification link from the submission page.
            </p>
            <a href="{{ route('home') }}" class="btn btn-outline-secondary">Return to the journal</a>
        </div>
    </div>
</div>
@endsection
