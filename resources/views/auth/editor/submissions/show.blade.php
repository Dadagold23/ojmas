@extends('layouts.app')

@section('content')
@if($errors->any())
    <div class="alert alert-danger rounded-4">
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

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

    .meta-badge{
        border-radius: 999px;
        padding: .55rem .9rem;
        font-size: .85rem;
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div>
            <h3 class="page-title mb-1">Submission Details</h3>
            <p class="text-muted mb-0">Editorial overview and action controls for this manuscript.</p>
        </div>
        <a href="{{ route('editor.submissions.index') }}" class="btn btn-secondary">Back</a>
    </div>

    <div class="card panel-card mb-4" data-aos="fade-up">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-light meta-badge">Submission No: {{ $submission->submission_no }}</span>
                <span class="badge bg-primary meta-badge">{{ ucfirst(str_replace('_', ' ', $submission->status)) }}</span>
                @if($submission->subject_area)
                    <span class="badge text-bg-warning meta-badge">{{ $submission->subject_area }}</span>
                @endif
            </div>

            <h2 class="fw-bold mb-3">{{ $submission->title }}</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <div><strong>Author:</strong> {{ $submission->author->name ?? 'N/A' }}</div>
                </div>
                <div class="col-md-6">
                    <div><strong>Submitted:</strong> {{ $submission->created_at?->format('d M Y') }}</div>
                </div>
                <div class="col-12">
                    <div><strong>Keywords:</strong> {{ $submission->keywords ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card panel-card mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-3">Abstract</h5>
        </div>
        <div class="card-body px-4 pb-4">
            {!! nl2br(e($submission->abstract)) !!}
        </div>
    </div>

    <div class="card panel-card mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-3">Co-Authors</h5>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="row g-3">
                @forelse($submission->articleAuthors ?? [] as $author)
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="fw-semibold">{{ $author->full_name }}</div>
                            <div class="text-muted small">{{ $author->email ?? 'No email' }}</div>
                            <div class="small mt-2">{{ $author->institution ?? 'Institution not provided' }}</div>
                            <div class="small text-muted">{{ $author->country ?? '' }}</div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-muted">No co-authors added.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card panel-card" data-aos="fade-up" data-aos-delay="200">
    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
        <h5 class="fw-bold mb-3">Editorial Actions</h5>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="border rounded-4 p-3 h-100">
                    <h6 class="fw-bold mb-3">Assign Reviewer</h6>

                    <form method="POST" action="{{ route('editor.submissions.assign-reviewer', $submission->id) }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reviewer</label>
                            <select name="reviewer_id" class="form-select" required>
                                <option value="">Select Reviewer</option>
                                @foreach($reviewers ?? [] as $reviewer)
                                    <option value="{{ $reviewer->id }}">{{ $reviewer->name }} ({{ $reviewer->email }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="{{ now()->addDays(14)->toDateString() }}">
                        </div>

                        <button type="submit" class="btn btn-outline-primary">Assign Reviewer</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="border rounded-4 p-3 h-100">
                    <h6 class="fw-bold mb-3">Request Revision</h6>

                    <form method="POST" action="{{ route('editor.submissions.request-revision', $submission->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" rows="4" class="form-control" placeholder="Add revision instructions for the author"></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-warning">Request Revision</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="border rounded-4 p-3 h-100">
                    <h6 class="fw-bold mb-3">Accept Submission</h6>

                    <form method="POST" action="{{ route('editor.submissions.accept', $submission->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" rows="3" class="form-control" placeholder="Optional acceptance note"></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-success">Accept</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="border rounded-4 p-3 h-100">
                    <h6 class="fw-bold mb-3">Reject Submission</h6>

                    <form method="POST" action="{{ route('editor.submissions.reject', $submission->id) }}">
                    @if($submission->status === 'accepted' || $submission->status === 'published')
                        <a href="{{ route('editor.submissions.publish.form', $submission->id) }}" class="btn btn-primary">
                            Publish to Issue
                        </a>
                    @endif

                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" rows="3" class="form-control" placeholder="Optional rejection reason"></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-danger">Reject</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
