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

    .issue-hero{
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(99,102,241,.95), rgba(15,23,42,.92)),
            url('{{ asset('images/backgrounds/issue-hero.jpg') }}') center center / cover no-repeat;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }

    .submission-pick{
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem;
        transition: .3s ease;
        background: #fff;
    }

    .submission-pick:hover{
        box-shadow: 0 12px 28px rgba(15,23,42,.06);
        border-color: #cbd5e1;
    }

    .article-row{
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem;
        background: #fff;
    }
</style>

<div class="container py-4">
    <div class="card issue-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-light text-primary mb-3 px-3 py-2">Issue Management</span>
                    <span class="badge bg-{{ $issue->status === 'published' ? 'success' : 'secondary' }} mb-3 px-3 py-2 ms-2">
                        {{ ucfirst($issue->status) }}
                    </span>
                    <h2 class="fw-bold mb-3">{{ $issue->title }}</h2>
                    <p class="mb-0 text-white-50">
                        @if($issue->volume)
                            Volume: {{ $issue->volume->title }}
                        @endif
                        @if($issue->issue_no)
                            | Issue No: {{ $issue->issue_no }}
                        @endif
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0 d-flex flex-wrap justify-content-lg-end gap-2">
                    <form method="POST" action="{{ route('editor.issues.toggle-status', $issue->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-warning fw-semibold">
                            <i class="bi bi-send me-1"></i>
                            {{ $issue->status === 'published' ? 'Set to Draft' : 'Publish Issue' }}
                        </button>
                    </form>
                    <a href="{{ route('editor.issues.index') }}" class="btn btn-outline-light">
                        Back to Issues
                    </a>
                </div>
            </div>
        </div>
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

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card panel-card" data-aos="fade-up">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1">Articles in this Issue</h5>
                    <p class="text-muted mb-3">Published articles currently attached to this issue.</p>
                </div>

                <div class="card-body px-4 pb-4">
                    <div class="row g-3">
                        @forelse($issue->articles ?? [] as $article)
                            <div class="col-12">
                                <div class="article-row">
                                    <div class="fw-bold mb-2">{{ $article->title }}</div>
                                    <div class="small text-muted mb-2">
                                        <i class="bi bi-person-circle me-1"></i>
                                        {{ $article->author->name ?? 'Unknown Author' }}
                                    </div>
                                    <div class="text-muted">
                                        {{ \Illuminate\Support\Str::limit($article->abstract, 180) }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-muted">
                                No articles attached to this issue yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card panel-card" data-aos="fade-up" data-aos-delay="100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1">Attach Accepted Articles</h5>
                    <p class="text-muted mb-3">Select accepted submissions to publish into this issue.</p>
                </div>

                <div class="card-body px-4 pb-4">
                    <form method="POST" action="{{ route('editor.issues.attach-articles', $issue->id) }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Published Date</label>
                            <input
                                type="date"
                                name="published_on"
                                class="form-control"
                                value="{{ old('published_on', now()->toDateString()) }}"
                            >
                        </div>

                        <div class="row g-3 mb-4">
                            @forelse($acceptedSubmissions as $submission)
                                <div class="col-12">
                                    <label class="submission-pick d-block">
                                        <div class="d-flex align-items-start gap-3">
                                            <input
                                                type="checkbox"
                                                name="submission_ids[]"
                                                value="{{ $submission->id }}"
                                                class="form-check-input mt-1"
                                            >
                                            <div>
                                                <div class="fw-semibold">{{ $submission->title }}</div>
                                                <div class="small text-muted">
                                                    {{ $submission->author->name ?? 'Unknown Author' }}
                                                </div>
                                                <div class="small text-muted">
                                                    Status: {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @empty
                                <div class="col-12 text-muted">
                                    No accepted submissions available for attachment.
                                </div>
                            @endforelse
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Attach Selected Articles
                        </button>
                    </form>
                </div>
            </div>

            <!-- Compiled Journal PDF Upload Card -->
            <div class="card panel-card mt-4" data-aos="fade-up" data-aos-delay="150">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1">Compiled Journal PDF</h5>
                    <p class="text-muted mb-3">Upload a single PDF containing all articles for this issue.</p>
                </div>

                <div class="card-body px-4 pb-4">
                    @if($issue->pdf_path)
                        <div class="alert alert-success d-flex align-items-center justify-content-between rounded-4 py-3 mb-0">
                            <div>
                                <i class="bi bi-file-earmark-pdf-fill fs-4 me-2 text-success"></i>
                                <span class="fw-semibold">Journal PDF Uploaded</span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('issue.pdf', $issue->id) }}" class="btn btn-sm btn-success" target="_blank">
                                    <i class="bi bi-download"></i> Download
                                </a>
                                <form method="POST" action="{{ route('editor.issues.delete-pdf', $issue->id) }}" onsubmit="return confirm('Delete this compiled PDF?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <form method="POST" action="{{ route('editor.issues.upload-pdf', $issue->id) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Upload Compiled PDF <span class="text-danger">*</span></label>
                                <input type="file" name="compiled_pdf" class="form-control" accept=".pdf" required>
                                <div class="form-text">PDF format only — max 40 MB</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-cloud-upload me-1"></i> Upload PDF
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
