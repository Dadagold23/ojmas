@extends('layouts.app')

@section('content')
<style>
    .author-hero {
        border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(13,110,253,.92), rgba(15,23,42,.92));
        color: #fff; overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .stat-card { border: none; border-radius: 1rem; box-shadow: 0 8px 24px rgba(15,23,42,.07); }
    .status-badge { font-size: .78rem; padding: .35em .75em; border-radius: 50px; }
</style>

<div class="container py-4">
    {{-- Hero --}}
    <div class="card author-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-light text-primary mb-3 px-3 py-2">Author Portal</span>
                    <h2 class="fw-bold mb-2">Welcome, {{ auth()->user()->name }}</h2>
                    <p class="mb-0 text-white-50">Manage your manuscript submissions and track their progress.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <a href="{{ route('author.submissions.create') }}" class="btn btn-warning btn-lg fw-semibold">
                        <i class="bi bi-plus-circle me-2"></i>New Submission
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4" data-aos="fade-up">
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center p-3">
                <div class="fs-2 fw-bold text-primary">{{ $stats['total'] }}</div>
                <div class="text-muted small">Total</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center p-3">
                <div class="fs-2 fw-bold text-info">{{ $stats['submitted'] }}</div>
                <div class="text-muted small">Submitted</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center p-3">
                <div class="fs-2 fw-bold text-warning">{{ $stats['under_review'] }}</div>
                <div class="text-muted small">Under Review</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center p-3">
                <div class="fs-2 fw-bold text-success">{{ $stats['published'] }}</div>
                <div class="text-muted small">Published</div>
            </div>
        </div>
    </div>

    {{-- Recent Submissions --}}
    <div class="card panel-card" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-1">Recent Submissions</h5>
                <p class="text-muted small mb-0">Your 5 most recent manuscripts.</p>
            </div>
            <a href="{{ route('author.submissions.index') }}" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Sub. No.</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th class="pe-4">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($submissions as $sub)
                        <tr>
                            <td class="px-4 fw-semibold text-primary">{{ $sub->submission_no }}</td>
                            <td>
                                <a href="{{ route('author.submissions.show', $sub) }}" class="text-decoration-none text-dark">
                                    {{ Str::limit($sub->title, 60) }}
                                </a>
                            </td>
                            <td>
                                @php
                                    $colors = ['pending_payment'=>'warning','submitted'=>'primary','under_review'=>'info','revision_requested'=>'warning',
                                               'accepted'=>'success','rejected'=>'danger','published'=>'dark'];
                                    $color = $colors[$sub->status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $color }} status-badge">{{ ucwords(str_replace('_',' ',$sub->status)) }}</span>
                            </td>
                            <td class="pe-4 text-muted small">{{ $sub->created_at->format('d M Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">
                                No submissions yet. <a href="{{ route('author.submissions.create') }}">Submit your first manuscript</a>.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
