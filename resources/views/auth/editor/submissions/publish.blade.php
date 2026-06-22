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

    .publish-hero{
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(16,185,129,.95), rgba(15,23,42,.92)),
            url('{{ asset('images/backgrounds/publish-hero.jpg') }}') center center / cover no-repeat;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }
</style>

<div class="container py-4">
    <div class="card publish-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-light text-success mb-3 px-3 py-2">Publication Workflow</span>
                    <h2 class="fw-bold mb-3">Publish Accepted Submission</h2>
                    <p class="mb-0 text-white-50">
                        Assign this accepted manuscript to a journal issue and finalize its publication record.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-journal-check" style="font-size: 5rem; opacity: .18;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title mb-1">Publish Submission</h3>
            <p class="text-muted mb-0">{{ $submission->title }}</p>
        </div>
        <a href="{{ route('editor.submissions.show', $submission->id) }}" class="btn btn-secondary">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger rounded-4">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card panel-card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('editor.submissions.publish', $submission->id) }}">
                @csrf

                <div class="row g-4">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Select Issue</label>
                        <select name="issue_id" class="form-select" required>
                            <option value="">Choose an issue</option>
                            @foreach($issues as $issue)
                                <option value="{{ $issue->id }}" @selected(old('issue_id') == $issue->id)>
                                    {{ $issue->title }} @if($issue->volume) - {{ $issue->volume->title }} @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Published Date</label>
                        <input
                            type="date"
                            name="published_on"
                            class="form-control"
                            value="{{ old('published_on', now()->toDateString()) }}"
                        >
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Full Text / Article Body</label>
                        <textarea
                            name="full_text"
                            rows="12"
                            class="form-control"
                            placeholder="Optional article full text or formatted content"
                        >{{ old('full_text') }}</textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-2"></i>Publish Submission
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
