@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .section-divider { border-top: 2px solid #e2e8f0; padding-top: 1.5rem; margin-top: 1.5rem; }
    .file-hint { font-size: .82rem; color: #64748b; }
</style>

<div class="container py-4" style="max-width:860px">
    <div class="d-flex align-items-center gap-3 mb-4" data-aos="fade-up">
        <a href="{{ route('author.submissions.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h3 class="fw-bold mb-0">New Manuscript Submission</h3>
            <p class="text-muted mb-0 small">Fill in all required fields and upload your manuscript.</p>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger rounded-4 mb-4">
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ route('author.submissions.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="card panel-card mb-4" data-aos="fade-up">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-file-text me-2 text-primary"></i>Manuscript Details</h5>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title') }}" placeholder="Full manuscript title" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Abstract <span class="text-danger">*</span></label>
                    <textarea name="abstract" rows="5" class="form-control @error('abstract') is-invalid @enderror"
                              placeholder="Structured abstract (250-300 words)" required>{{ old('abstract') }}</textarea>
                    @error('abstract')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Keywords</label>
                        <input type="text" name="keywords" class="form-control" value="{{ old('keywords') }}"
                               placeholder="e.g. machine learning, NLP, deep learning">
                        <div class="file-hint mt-1">Separate with commas</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Subject Area</label>
                        <input type="text" name="subject_area" class="form-control" value="{{ old('subject_area') }}"
                               placeholder="e.g. Computer Science">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Your ORCID <span class="text-muted fw-normal">(optional, will appear in the published byline)</span></label>
                        <input type="text" name="author_orcid" class="form-control" value="{{ old('author_orcid', auth()->user()->orcid) }}"
                               placeholder="0000-0000-0000-0000">
                        <div class="file-hint mt-1">16 digits, with or without hyphens</div>
                        @error('author_orcid')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card panel-card mb-4" data-aos="fade-up">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-paperclip me-2 text-primary"></i>File Uploads</h5>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Manuscript <span class="text-danger">*</span></label>
                    <input type="file" name="manuscript" class="form-control @error('manuscript') is-invalid @enderror" accept=".pdf,.doc,.docx" required>
                    <div class="file-hint mt-1">PDF, DOC, DOCX - max 1 GB</div>
                    @error('manuscript')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Cover Letter</label>
                    <input type="file" name="cover_letter" class="form-control @error('cover_letter') is-invalid @enderror" accept=".pdf,.doc,.docx">
                    <div class="file-hint mt-1">PDF, DOC, DOCX - max 1 GB (optional)</div>
                    @error('cover_letter')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-2">
                    <label class="form-label fw-semibold">Supplementary File</label>
                    <input type="file" name="supplementary" class="form-control @error('supplementary') is-invalid @enderror" accept=".pdf,.doc,.docx,.zip">
                    <div class="file-hint mt-1">PDF, DOC, DOCX, ZIP - max 1 GB (optional)</div>
                    @error('supplementary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card panel-card mb-4" data-aos="fade-up">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Co-Authors</h5>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addCoAuthor">
                        <i class="bi bi-plus"></i> Add Co-Author
                    </button>
                </div>
                <div id="coAuthorList"></div>
            </div>
        </div>

        <div class="d-flex gap-3 justify-content-end" data-aos="fade-up">
            <a href="{{ route('author.submissions.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-5 fw-semibold">
                <i class="bi bi-send me-2"></i>Submit Manuscript
            </button>
        </div>
    </form>
</div>

<template id="coAuthorTemplate">
    <div class="co-author-row border rounded-3 p-3 mb-3 bg-light">
        <div class="row g-2">
            <div class="col-md-6">
                <input type="text" name="co_authors[__i__][full_name]" class="form-control form-control-sm" placeholder="Full Name">
            </div>
            <div class="col-md-6">
                <input type="email" name="co_authors[__i__][email]" class="form-control form-control-sm" placeholder="Email">
            </div>
            <div class="col-md-4">
                <input type="text" name="co_authors[__i__][institution]" class="form-control form-control-sm" placeholder="Institution">
            </div>
            <div class="col-md-3">
                <input type="text" name="co_authors[__i__][orcid]" class="form-control form-control-sm" placeholder="ORCID (optional, 16 digits)">
            </div>
            <div class="col-md-3">
                <input type="text" name="co_authors[__i__][country]" class="form-control form-control-sm" placeholder="Country">
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-co-author">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
        </div>
    </div>
</template>

<script>
    let coAuthorIndex = 0;
    document.getElementById('addCoAuthor').addEventListener('click', () => {
        const tpl = document.getElementById('coAuthorTemplate').innerHTML.replaceAll('__i__', coAuthorIndex++);
        const div = document.createElement('div');
        div.innerHTML = tpl;
        document.getElementById('coAuthorList').appendChild(div.firstElementChild);
    });
    document.getElementById('coAuthorList').addEventListener('click', e => {
        if (e.target.closest('.remove-co-author')) e.target.closest('.co-author-row').remove();
    });
</script>
@endsection
