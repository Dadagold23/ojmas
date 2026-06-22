@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
</style>

<div class="container py-4" style="max-width:760px">
    <div class="d-flex align-items-center gap-3 mb-4" data-aos="fade-up">
        <a href="{{ route('admin.publications.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h3 class="fw-bold mb-0">Publish Article</h3>
            <p class="text-muted small mb-0">Upload the final PDF and assign to a journal issue.</p>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger rounded-4 mb-4">
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <div class="card panel-card" data-aos="fade-up">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.publications.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Accepted Submission <span class="text-danger">*</span></label>
                    <select name="submission_id" class="form-select @error('submission_id') is-invalid @enderror" required>
                        <option value="">— Select accepted submission —</option>
                        @foreach($submissions as $sub)
                        <option value="{{ $sub->id }}" {{ old('submission_id') == $sub->id ? 'selected' : '' }}>
                            {{ $sub->submission_no }} — {{ Str::limit($sub->title, 60) }}
                            ({{ $sub->author->name ?? 'N/A' }})
                        </option>
                        @endforeach
                    </select>
                    @error('submission_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if($submissions->isEmpty())
                    <div class="form-text text-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        No accepted submissions available. Accept a submission first.
                    </div>
                    @endif
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Journal Issue <span class="text-danger">*</span></label>
                    <select name="issue_id" class="form-select @error('issue_id') is-invalid @enderror" required>
                        <option value="">— Select issue —</option>
                        @foreach($issues as $issue)
                        <option value="{{ $issue->id }}" {{ old('issue_id') == $issue->id ? 'selected' : '' }}>
                            {{ $issue->title }}@if($issue->volume) — {{ $issue->volume->title }}@endif
                        </option>
                        @endforeach
                    </select>
                    @error('issue_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Published PDF <span class="text-danger">*</span></label>
                    <input type="file" name="published_pdf"
                           class="form-control @error('published_pdf') is-invalid @enderror"
                           accept=".pdf" required>
                    <div class="form-text">PDF only — max 20 MB</div>
                    @error('published_pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Publication Note</label>
                    <textarea name="note" rows="3" class="form-control @error('note') is-invalid @enderror"
                              placeholder="Optional note about this publication...">{{ old('note') }}</textarea>
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-flex gap-3 justify-content-end">
                    <a href="{{ route('admin.publications.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    <button type="submit" class="btn btn-success px-5 fw-semibold">
                        <i class="bi bi-cloud-upload me-2"></i>Publish & Notify Author
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
