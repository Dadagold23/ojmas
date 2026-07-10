@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .publish-badge {
        display: inline-flex; align-items: center; gap: .45rem;
        background: linear-gradient(135deg,#10b981,#059669);
        color:#fff; border-radius: 2rem; padding: .25rem .85rem;
        font-size: .78rem; font-weight: 600; letter-spacing: .03em;
    }
    .notify-toggle { cursor: pointer; }
    .notify-toggle input[type=checkbox] { width: 1.1rem; height: 1.1rem; cursor: pointer; }
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

                {{-- Article Title --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Article Title <span class="text-danger">*</span></label>
                    <input type="text" name="title"
                           class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title') }}"
                           placeholder="Enter article title..." required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Author --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Author <span class="text-danger">*</span></label>
                    <select name="author_id" class="form-select @error('author_id') is-invalid @enderror" required>
                        <option value="">— Select author user —</option>
                        @foreach($authors as $author)
                        <option value="{{ $author->id }}" {{ old('author_id') == $author->id ? 'selected' : '' }}>
                            {{ $author->name }} &lt;{{ $author->email }}&gt;
                        </option>
                        @endforeach
                    </select>
                    @error('author_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Abstract --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Abstract <span class="text-danger">*</span></label>
                    <textarea name="abstract" rows="5"
                              class="form-control @error('abstract') is-invalid @enderror"
                              placeholder="Enter article abstract...">{{ old('abstract') }}</textarea>
                    @error('abstract')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Keywords --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Keywords</label>
                    <input type="text" name="keywords"
                           class="form-control @error('keywords') is-invalid @enderror"
                           value="{{ old('keywords') }}"
                           placeholder="e.g. machine learning, education, artificial intelligence">
                    <div class="form-text">Separate keywords with commas.</div>
                    @error('keywords')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Subject Area --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Subject Area</label>
                    <input type="text" name="subject_area"
                           class="form-control @error('subject_area') is-invalid @enderror"
                           value="{{ old('subject_area') }}"
                           placeholder="e.g. Science, Humanities, Agriculture">
                    @error('subject_area')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Journal Issue --}}
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

                {{-- Published PDF --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Published PDF <span class="text-danger">*</span></label>
                    <input type="file" name="published_pdf"
                           class="form-control @error('published_pdf') is-invalid @enderror"
                           accept=".pdf" required>
                    <div class="form-text">PDF only — max 20 MB</div>
                    @error('published_pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Publication Note --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Publication Note</label>
                    <textarea name="note" rows="3"
                              class="form-control @error('note') is-invalid @enderror"
                              placeholder="Optional note about this publication...">{{ old('note') }}</textarea>
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Notify Author toggle --}}
                <div class="mb-4 p-3 bg-light rounded-3 border">
                    <label class="notify-toggle d-flex align-items-center gap-2 mb-0 user-select-none">
                        <input type="checkbox" name="notify_author" value="1"
                               {{ old('notify_author', '1') == '1' ? 'checked' : '' }}
                               class="form-check-input mt-0">
                        <span class="fw-semibold small">
                            <i class="bi bi-bell me-1 text-primary"></i>
                            Notify Author by Email
                        </span>
                    </label>
                    <div class="text-muted" style="font-size:.78rem; margin-top:.3rem; padding-left:1.6rem">
                        Send an email notification to the author when this article is published.
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex gap-3 justify-content-end">
                    <a href="{{ route('admin.publications.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    <button type="submit" class="btn btn-success px-5 fw-semibold">
                        <i class="bi bi-cloud-upload me-2"></i>Publish Now
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection
