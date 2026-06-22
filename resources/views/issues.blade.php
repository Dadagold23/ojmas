@extends('layouts.app')

@section('content')
<style>
    .issues-hero {
        border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(99,102,241,.95), rgba(15,23,42,.92));
        color: #fff; box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .issue-card { border: 1px solid #e2e8f0; border-radius: 1rem; transition: .25s ease; }
    .issue-card:hover { border-color: #cbd5e1; transform: translateY(-3px); box-shadow: 0 12px 28px rgba(15,23,42,.07); }
</style>

<div class="container py-4">

    {{-- Hero --}}
    <div class="card issues-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-2">Browse All Issues</h2>
                    <p class="mb-0 text-white-50">Explore published volumes and issues of the OJMAS.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <i class="bi bi-journals" style="font-size:5rem; opacity:.15"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card panel-card mb-4" data-aos="fade-up">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('issues.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Search</label>
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Issue title or number..." value="{{ $search ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Volume</label>
                    <select name="volume" class="form-select form-select-sm">
                        <option value="">All Volumes</option>
                        @foreach($volumes ?? [] as $vol)
                        <option value="{{ $vol }}" {{ ($volume ?? '') === $vol ? 'selected' : '' }}>{{ $vol }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Year</label>
                    <select name="year" class="form-select form-select-sm">
                        <option value="">All Years</option>
                        @foreach($years ?? [] as $y)
                        <option value="{{ $y }}" {{ ($year ?? '') == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="{{ route('issues.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Issues grid --}}
    <div class="row g-4" data-aos="fade-up">
        @forelse($issues as $issue)
        <div class="col-md-6 col-lg-4">
            <div class="issue-card p-4 h-100 d-flex flex-column bg-white">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;font-size:1.4rem;flex-shrink:0">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <div>
                        <div class="fw-bold">{{ $issue->title }}</div>
                        @if($issue->volume)
                        <div class="text-muted small">{{ $issue->volume->title }}</div>
                        @endif
                    </div>
                </div>

                @if($issue->description)
                <p class="text-muted small mb-3">{{ Str::limit($issue->description, 100) }}</p>
                @endif

                <div class="d-flex flex-wrap gap-2 mb-3 mt-auto">
                    @if($issue->issue_no)
                    <span class="badge bg-light text-dark border">Issue {{ $issue->issue_no }}</span>
                    @endif
                    <span class="badge bg-light text-dark border">
                        {{ $issue->articles->count() }} article{{ $issue->articles->count() !== 1 ? 's' : '' }}
                    </span>
                    @if($issue->published_on)
                    <span class="badge bg-light text-dark border">
                        {{ \Carbon\Carbon::parse($issue->published_on)->format('M Y') }}
                    </span>
                    @endif
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('issue.show', $issue->id) }}" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-eye me-1"></i>View Issue
                    </a>
                    <a href="{{ route('issue.pdf', $issue->id) }}" class="btn btn-outline-secondary btn-sm" title="Download PDF">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </a>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="text-center text-muted py-5">
                <i class="bi bi-journals fs-1 d-block mb-3 opacity-25"></i>
                No issues found.
            </div>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($issues->hasPages())
    <div class="mt-4" data-aos="fade-up">
        {{ $issues->links() }}
    </div>
    @endif

</div>
@endsection
