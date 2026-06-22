@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
</style>

<div class="container py-4" style="max-width:720px">
    <div class="d-flex align-items-center gap-3 mb-4" data-aos="fade-up">
        <a href="{{ route('admin.publications.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h3 class="fw-bold mb-0">Edit Publication</h3>
            <p class="text-muted small mb-0">{{ Str::limit($publication->article->title ?? '', 60) }}</p>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger rounded-4 mb-4">
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <div class="card panel-card" data-aos="fade-up">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.publications.update', $publication) }}" enctype="multipart/form-data">
                @csrf @method('PUT')

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Journal Issue <span class="text-danger">*</span></label>
                    <select name="issue_id" class="form-select @error('issue_id') is-invalid @enderror" required>
                        <option value="">— Select issue —</option>
                        @foreach($issues as $issue)
                        <option value="{{ $issue->id }}"
                            {{ old('issue_id', $publication->article->issue_id) == $issue->id ? 'selected' : '' }}>
                            {{ $issue->title }}@if($issue->volume) — {{ $issue->volume->title }}@endif
                        </option>
                        @endforeach
                    </select>
                    @error('issue_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Replace PDF</label>
                    @if($publication->published_pdf_path)
                    <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-light rounded-3">
                        <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                        <span class="small text-muted">Current PDF uploaded</span>
                        <a href="{{ route('admin.publications.download', $publication) }}"
                           class="btn btn-sm btn-outline-success ms-auto">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                    @endif
                    <input type="file" name="published_pdf"
                           class="form-control @error('published_pdf') is-invalid @enderror"
                           accept=".pdf">
                    <div class="form-text">Leave blank to keep existing PDF. PDF only — max 20 MB.</div>
                    @error('published_pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Publication Note</label>
                    <textarea name="note" rows="3" class="form-control @error('note') is-invalid @enderror"
                              placeholder="Optional note...">{{ old('note', $publication->note) }}</textarea>
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-flex gap-3 justify-content-end">
                    <a href="{{ route('admin.publications.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-5 fw-semibold">
                        <i class="bi bi-check-lg me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
