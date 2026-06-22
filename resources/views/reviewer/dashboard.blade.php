@extends('layouts.app')

@section('content')
<style>
    .dash-hero{
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(16,185,129,.95), rgba(15,23,42,.92)),
            url('{{ asset('images/backgrounds/reviewer-hero.jpg') }}') center center / cover no-repeat;
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
                    <h2 class="fw-bold mb-3">Reviewer Dashboard</h2>
                    <p class="mb-0 text-white-50">
                        Review assigned manuscripts, track due dates, and submit editorial recommendations with confidence.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-clipboard-check" style="font-size: 5rem; opacity: .18;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4" data-aos="fade-up">
        <h3 class="dash-title mb-1">Reviewer Dashboard</h3>
        <p class="text-muted mb-0">Your assignment summary and recent review activity.</p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4" data-aos="fade-up">
            <div class="card dash-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <div class="text-muted small mb-2">Active Assignments</div>
                            <h2 class="fw-bold mb-0">{{ $stats['active'] ?? 0 }}</h2>
                        </div>
                        <div class="dash-stat-icon bg-primary-subtle text-primary">
                            <i class="bi bi-journal-text"></i>
                        </div>
                    </div>
                    <small class="text-muted">Assignments awaiting your review input.</small>
                </div>
            </div>
        </div>

        <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card dash-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <div class="text-muted small mb-2">Completed Reviews</div>
                            <h2 class="fw-bold mb-0">{{ $stats['completed'] ?? 0 }}</h2>
                        </div>
                        <div class="dash-stat-icon bg-success-subtle text-success">
                            <i class="bi bi-check2-square"></i>
                        </div>
                    </div>
                    <small class="text-muted">Reviews already submitted by you.</small>
                </div>
            </div>
        </div>

        <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
            <div class="card dash-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <div class="text-muted small mb-2">Due Soon</div>
                            <h2 class="fw-bold mb-0">{{ $stats['due_soon'] ?? 0 }}</h2>
                        </div>
                        <div class="dash-stat-icon bg-warning-subtle text-warning">
                            <i class="bi bi-alarm"></i>
                        </div>
                    </div>
                    <small class="text-muted">Assignments closing within the next 7 days.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card dash-panel" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-1">Recent Assignments</h5>
                <p class="text-muted mb-0">Latest manuscripts assigned to you.</p>
            </div>
            <a href="{{ route('reviewer.assignments.index') }}" class="btn btn-outline-primary btn-sm">
                View All
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Manuscript</th>
                            <th>Due Date</th>
                            <th>Files</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assignments ?? [] as $assignment)
                        @php
                            $primaryFile = $assignment->submission->files->firstWhere('file_type', 'manuscript')
                                ?? $assignment->submission->files->first();
                        @endphp
                        <tr>
                            <td class="px-4">
                                <div class="fw-semibold small">{{ Str::limit($assignment->submission->title ?? 'Untitled', 50) }}</div>
                                <div class="text-muted" style="font-size:.75rem">{{ $assignment->submission->submission_no ?? '' }}</div>
                            </td>
                            <td class="small text-muted">{{ $assignment->due_date?->format('d M Y') ?? 'N/A' }}</td>
                            <td class="small text-muted">{{ $assignment->submission->files->count() }} file(s)</td>
                            <td>
                                <span class="badge bg-{{ $assignment->status === 'completed' ? 'success' : 'warning' }}">
                                    {{ ucfirst($assignment->status ?? 'pending') }}
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="d-inline-flex gap-2">
                                    @if($primaryFile)
                                    <a href="{{ route('reviewer.submissions.files.download', [$assignment->submission, $primaryFile]) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i>Download Manuscript
                                    </a>
                                    @endif
                                    <a href="{{ route('reviewer.assignments.show', $assignment->id) }}"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye me-1"></i>Open
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No recent assignments.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
