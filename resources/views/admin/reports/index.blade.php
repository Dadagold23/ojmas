@extends('layouts.app')

@section('content')
<style>
    .page-title{
        font-weight: 800;
        color: #0f172a;
    }

    .report-hero{
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(99,102,241,.95), rgba(15,23,42,.92)),
            url('{{ asset('images/backgrounds/report-hero.jpg') }}') center center / cover no-repeat;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }

    .stat-card,
    .panel-card{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }
</style>

<div class="container py-4">
    <div class="card report-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-3">Journal Reports Dashboard</h2>
                    <p class="mb-0 text-white-50">
                        Review key reporting metrics on authors, reviewers, submissions, and revenue.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-bar-chart-line" style="font-size: 5rem; opacity: .18;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4" data-aos="fade-up">
        <div>
            <h3 class="page-title mb-1">Reports</h3>
            <p class="text-muted mb-0">Filter and review journal statistics.</p>
        </div>

        <form method="GET" class="d-flex flex-wrap gap-2">
            <input type="date" name="from" class="form-control" value="{{ request('from') }}">
            <input type="date" name="to" class="form-control" value="{{ request('to') }}">
            <button class="btn btn-primary">Filter</button>
        </form>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4" data-aos="fade-up">
            <div class="card stat-card h-100">
                <div class="card-body p-4">
                    <div class="text-muted small mb-2">Total Authors</div>
                    <h2 class="fw-bold mb-0">{{ $report['authors'] ?? 0 }}</h2>
                </div>
            </div>
        </div>

        <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card stat-card h-100">
                <div class="card-body p-4">
                    <div class="text-muted small mb-2">Total Reviewers</div>
                    <h2 class="fw-bold mb-0">{{ $report['reviewers'] ?? 0 }}</h2>
                </div>
            </div>
        </div>

        <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
            <div class="card stat-card h-100">
                <div class="card-body p-4">
                    <div class="text-muted small mb-2">Revenue</div>
                    <h2 class="fw-bold mb-0">₦{{ number_format($report['revenue'] ?? 0, 2) }}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card panel-card" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Submission Status Breakdown</h5>
            <p class="text-muted mb-3">Distribution of submissions by workflow status.</p>
        </div>

        <div class="card-body px-4 pb-4">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($report['status_breakdown'] ?? []) as $status => $count)
                            <tr>
                                <td>{{ ucfirst(str_replace('_', ' ', $status)) }}</td>
                                <td>{{ $count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">No report data available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
