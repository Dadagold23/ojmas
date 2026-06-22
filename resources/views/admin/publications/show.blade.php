@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .info-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 600; }
</style>

<div class="container py-4" style="max-width:860px">
    <div class="d-flex align-items-center gap-3 mb-4" data-aos="fade-up">
        <a href="{{ route('admin.publications.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h3 class="fw-bold mb-0">{{ Str::limit($publication->article->title ?? '—', 70) }}</h3>
            <span class="text-muted small">{{ $publication->article->submission->submission_no ?? '' }}</span>
        </div>
        <a href="{{ route('admin.publications.edit', $publication) }}" class="btn btn-primary btn-sm fw-semibold">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-4"><i class="bi bi-journal-check me-2 text-success"></i>Publication Details</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-label mb-1">Author</div>
                            <div class="small">{{ $publication->article->submission->author->name ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label mb-1">Issue</div>
                            <div class="small">{{ $publication->article->issue->title ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label mb-1">Volume</div>
                            <div class="small">{{ $publication->article->issue->volume->title ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label mb-1">Published On</div>
                            <div class="small">{{ $publication->published_on?->format('d M Y, H:i') ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label mb-1">Published By</div>
                            <div class="small">{{ $publication->publisher->name ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label mb-1">Status</div>
                            <span class="badge bg-success">Published</span>
                        </div>
                        @if($publication->note)
                        <div class="col-12">
                            <div class="info-label mb-1">Note</div>
                            <div class="small">{{ $publication->note }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Submission files --}}
            @if($publication->article->submission->files->count())
            <div class="card panel-card" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-folder2-open me-2 text-primary"></i>Submission Files</h6>
                    @foreach($publication->article->submission->files as $file)
                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                        <div>
                            <div class="small fw-semibold">{{ $file->original_name }}</div>
                            <div class="text-muted" style="font-size:.75rem">
                                {{ ucwords(str_replace('_',' ',$file->file_type)) }} &middot; {{ number_format($file->file_size/1024,1) }} KB
                            </div>
                        </div>
                        <a href="{{ route('admin.submissions.files.download', [$publication->article->submission, $file]) }}"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-5">
            {{-- Published PDF --}}
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4 text-center">
                    <i class="bi bi-file-earmark-pdf text-danger" style="font-size:3rem"></i>
                    <h6 class="fw-bold mt-3 mb-2">Published PDF</h6>
                    @if($publication->published_pdf_path)
                    <p class="text-muted small mb-3">Final published article PDF.</p>
                    <a href="{{ route('admin.publications.download', $publication) }}"
                       class="btn btn-success w-100 fw-semibold">
                        <i class="bi bi-download me-2"></i>Download PDF
                    </a>
                    @else
                    <p class="text-muted small mb-0">No PDF uploaded.</p>
                    @endif
                </div>
            </div>

            {{-- Danger zone --}}
            <div class="card border-danger" style="border-radius:1.25rem" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-danger mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h6>
                    <p class="text-muted small mb-3">Remove this publication record. The submission will revert to accepted status.</p>
                    <form method="POST" action="{{ route('admin.publications.destroy', $publication) }}"
                          onsubmit="return confirm('Remove this publication? The submission will revert to accepted.')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm fw-semibold w-100">
                            <i class="bi bi-trash me-1"></i>Remove Publication
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
