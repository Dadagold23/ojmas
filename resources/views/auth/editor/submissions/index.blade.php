@extends('layouts.app')

@section('content')
<style>
    .page-title{
        font-weight: 800;
        color: #0f172a;
    }

    .panel-card{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }

    .editor-hero{
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(13,110,253,.95), rgba(15,23,42,.92)),
            url('{{ asset('images/backgrounds/editor-hero.jpg') }}') center center / cover no-repeat;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }
</style>

<div class="container py-4">
    <div class="card editor-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-light text-primary mb-3 px-3 py-2">Editorial Management</span>
                    <h2 class="fw-bold mb-3">Manage Journal Submissions</h2>
                    <p class="mb-0 text-white-50">
                        Review incoming manuscripts, inspect details, and move them through the editorial workflow.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-files" style="font-size: 5rem; opacity: .18;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4" data-aos="fade-up">
        <h3 class="page-title mb-1">Editor Submissions</h3>
        <p class="text-muted mb-0">All manuscripts currently available for editorial handling.</p>
    </div>

    <div class="card panel-card" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Submission List</h5>
            <p class="text-muted mb-3">Open any submission to review abstract, co-authors, and available actions.</p>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Submission No</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="pe-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($submissions ?? [] as $submission)
                            <tr>
                                <td class="px-4 fw-semibold">{{ $submission->submission_no }}</td>
                                <td>{{ $submission->title }}</td>
                                <td>{{ $submission->author->name ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge bg-primary">
                                        {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                    </span>
                                </td>
                                <td>{{ $submission->created_at?->format('d M Y') }}</td>
                                <td class="pe-4">
                                    <a href="{{ route('editor.submissions.show', $submission->id) }}" class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">No submissions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if(method_exists($submissions ?? null, 'links'))
        <div class="mt-4">
            {{ $submissions->links() }}
        </div>
    @endif
</div>
@endsection
