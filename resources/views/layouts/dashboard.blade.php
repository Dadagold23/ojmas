@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card soft-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Author Panel</h5>
                    <div class="d-grid gap-2">
                        <a href="{{ route('author.dashboard') }}" class="btn btn-outline-primary">Dashboard</a>
                        <a href="{{ route('author.submissions.index') }}" class="btn btn-outline-primary">My Submissions</a>
                        <a href="{{ route('author.submissions.create') }}" class="btn btn-primary">New Submission</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-9">
            @yield('dashboard-content')
        </div>
    </div>
</div>
@endsection
