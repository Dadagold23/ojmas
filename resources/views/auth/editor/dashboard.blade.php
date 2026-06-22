@extends('layouts.app')

@section('content')
<style>
    .dash-hero{
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(13,110,253,.95), rgba(15,23,42,.92)),
            url('{{ asset('images/backgrounds/editor-hero.jpg') }}') center center / cover no-repeat;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }

    .dash-title{
        font-weight: 800;
        color: #0f172a;
    }

    .dash-stat-card{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
        transition: .3s ease;
    }

    .dash-stat-card:hover{
        transform: translateY(-4px);
    }

    .dash-stat-icon{
        width: 58px;
        height: 58px;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }

    .dash-panel{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }
</style>

<div class="container py-4">
    <div class="card dash-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-light text-primary mb-3 px-3 py-2">Editor Workspace</span>
                    <h2 class="fw-bold mb-3">Editorial Dashboard</h2>
                    <p class="mb-0 text-white-50">
                        Manage submissions, coordinate peer review, track decisions, and prepare accepted articles for publication.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-pencil-square" style="font-size: 5rem; opacity: .18;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4" data-aos="fade-up">
        <h3 class="dash-title mb-1">Editor Dashboard</h3>
        <p class="text-muted mb-0">Overview of submissions and editorial activities.</p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3" data-aos="fade-up">
            <div class="card dash-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <div class="text-muted small mb-2">Assigned Submissions</div>
                            <h2 class="fw-bold mb-0">{{ $stats['assigned'] ?? 0 }}</h2>
                        </div>
                        <div class="dash-stat-icon bg-primary-subtle text-primary">
                            <i class="bi bi-inbox"></i>
                        </div>
                    </div>
                    <small class="text-muted">All submissions currently in editorial scope.</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="100">
            <div class="card dash-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <div class="text-muted small mb-2">Under Review</div>
                            <h2 class="fw-bold mb-0">{{ $stats['under_review'] ?? 0 }}</h2>
                        </div>
                        <div class="dash-stat-icon bg-warning-subtle text-warning">
                            <i class="bi bi-search"></i>
                        </div>
                    </div>
                    <small class="text-muted">Manuscripts presently under peer review.</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="200">
            <div class="card dash-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <div class="text-muted small mb-2">Accepted</div>
                            <h2 class="fw-bold mb-0">{{ $stats['accepted'] ?? 0 }}</h2>
                        </div>
                        <div class="dash-stat-icon bg-success-subtle text-success">
                            <i class="bi bi-patch-check"></i>
                        </div>
                    </div>
                    <small class="text-muted">Manuscripts approved for publication.</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="300">
            <div class="card dash-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <div class="text-muted small mb-2">Rejected</div>
                            <h2 class="fw-bold mb-0">{{ $stats['rejected'] ?? 0 }}</h2>
                        </div>
                        <div class="dash-stat-icon bg-danger-subtle text-danger">
                            <i class="bi bi-x-octagon"></i>
                        </div>
                    </div>
                    <small class="text-muted">Manuscripts declined after review.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card dash-panel" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Recent Editorial Activity</h5>
            <p class="text-muted mb-3">Latest actions and workflow updates.</p>
        </div>
        <div class="card-body px-4 pb-4">
            <ul class="list-group list-group-flush">
                @forelse($activities ?? [] as $activity)
                    <li class="list-group-item px-0">{{ $activity }}</li>
                @empty
                    <li class="list-group-item px-0 text-muted">No activity available.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
