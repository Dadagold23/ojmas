@extends('layouts.app')

@section('hide_chrome', '')

@section('content')
<style>
    .article-hero{
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(15,23,42,.96), rgba(13,110,253,.90)),
            url('{{ asset('images/backgrounds/article-hero.jpg') }}') center center / cover no-repeat;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }

    .content-card,
    .meta-card{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }

    .section-title{
        font-weight: 800;
        color: #0f172a;
    }

    .article-content{
        line-height: 1.9;
        color: #334155;
    }

    .meta-label{
        font-size: .82rem;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: .4px;
    }

    .meta-value{
        font-weight: 600;
        color: #0f172a;
    }

    .pdf-preview{
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
        max-width: 800px;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
    }

    .pdf-body{
        padding: 28px;
        line-height: 1.8;
        color: #1e293b;
        font-size: 14px;
    }

    .pdf-body .article-title-pdf{
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 10px;
        line-height: 1.3;
    }

    .pdf-body .pdf-meta{
        font-size: 12px;
        color: #64748b;
        margin-bottom: 4px;
    }

    .pdf-body .section-heading{
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
        margin-top: 20px;
        margin-bottom: 8px;
        border-left: 3px solid #0d6efd;
        padding-left: 10px;
    }

    .pdf-body .content-box{
        border: 1px solid #e2e8f0;
        padding: 12px 14px;
        border-radius: 6px;
        margin-bottom: 14px;
        background: #f8fafc;
    }

    /* Renders the article's actual PDF rather than a styled summary. */
    .pdf-embed{
        display: block;
        width: 100%;
        max-width: 900px;
        height: 80vh;
        min-height: 520px;
        margin: 0 auto;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
        background: #fff;
    }
</style>

<div class="container py-4">
    <div class="card article-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-9">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @if(!empty($article->subject_area))
                            <span class="badge bg-warning text-dark px-3 py-2">{{ $article->subject_area }}</span>
                        @endif
                    </div>

                    <h1 class="fw-bold mb-3">{{ $article->title }}</h1>

                    <p class="mb-0 text-white-50">
                        Scholarly article published on OJMAS.
                    </p>
                </div>

                <div class="col-lg-3 text-lg-end mt-4 mt-lg-0">
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('archives') }}" class="btn btn-outline-light">
                            <i class="bi bi-arrow-left me-2"></i>Back to Archives
                        </a>
                        <a href="{{ route('article.pdf', $article->slug) }}" class="btn btn-warning">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Download PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card content-card" data-aos="fade-up">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h4 class="fw-bold mb-3">Abstract</h4>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="article-content">
                        {!! nl2br(e($article->abstract ?? '')) !!}
                    </div>
                </div>
            </div>

            @if(!empty($article->keywords))
                <div class="card content-card mt-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <h5 class="fw-bold mb-3">Keywords</h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="article-content">
                            {{ $article->keywords }}
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card meta-card" data-aos="fade-left">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-3">Article Details</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3">
                        <div class="meta-label">Author</div>
                        <div class="meta-value">{{ $article->author->name ?? 'Unknown Author' }}</div>
                    </div>

                    @if(!empty($article->submission_no))
                        <div class="mb-3">
                            <div class="meta-label">Submission No</div>
                            <div class="meta-value">{{ $article->submission_no }}</div>
                        </div>
                    @endif

                    @if(!empty($article->subject_area))
                        <div class="mb-3">
                            <div class="meta-label">Subject Area</div>
                            <div class="meta-value">{{ $article->subject_area }}</div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <div class="meta-label">Published Date</div>
                        <div class="meta-value">{{ $article->created_at?->format('d M Y') }}</div>
                    </div>

                    <div class="mb-0">
                        <div class="meta-label">Status</div>
                        <div class="meta-value">{{ ucfirst($article->status ?? 'published') }}</div>
                    </div>
                </div>
            </div>

            <div class="card meta-card mt-4" data-aos="fade-left" data-aos-delay="100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-3">Co-Authors</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    @php
                        $coAuthors = $article->submission?->articleAuthors?->sortBy('author_order') ?? collect();
                    @endphp

                    @forelse($coAuthors as $coAuthor)
                        <div class="border rounded-4 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="fw-semibold">{{ $coAuthor->full_name }}</div>
                                @if($coAuthor->is_corresponding)
                                    <span class="badge bg-primary-subtle text-primary text-uppercase">Corresponding</span>
                                @endif
                            </div>
                            <div class="small text-muted">{{ $coAuthor->email ?? 'No email provided' }}</div>
                            <div class="small mt-1">{{ $coAuthor->institution ?? 'Institution not provided' }}</div>
                            <div class="small text-muted">{{ $coAuthor->country ?? '' }}</div>
                        </div>
                    @empty
                        <div class="text-muted">No co-authors listed.</div>
                    @endforelse

                    @if(!empty($article->issue))
                        <div class="mb-3">
                            <div class="meta-label">Issue</div>
                            <div class="meta-value">
                                <a href="{{ route('issue.show', $article->issue->id) }}" class="text-decoration-none">
                                    {{ $article->issue->title }}
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(!empty($article->issue?->volume))
                        <div class="mb-3">
                            <div class="meta-label">Volume</div>
                            <div class="meta-value">{{ $article->issue->volume->title }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- PDF Preview with Header & Footer --}}
    <div class="mt-5" data-aos="fade-up">
        <h4 class="fw-bold mb-3 text-center">
            <i class="bi bi-file-earmark-text me-2"></i>Article Preview
        </h4>

        @if(!empty($article->full_text))
            <div class="pdf-preview">
                <div class="pdf-body">
                    <div class="article-title-pdf">{{ $article->title }}</div>
                    <div class="pdf-meta"><strong>Author:</strong> {{ $article->author->name ?? 'Unknown Author' }}</div>
                    <div class="pdf-meta"><strong>Published:</strong> {{ $article->published_on?->format('d M Y') ?? ($article->created_at?->format('d M Y') ?? '-') }}</div>

                    <div class="section-heading">Full Text</div>
                    <div>
                        {!! nl2br(e(strip_tags($article->full_text))) !!}
                    </div>
                </div>
            </div>
        @else
            {{-- No stored body text: show the article's actual PDF. --}}
            <iframe
                src="{{ route('article.preview', $article->slug) }}#toolbar=0&view=FitH"
                title="{{ $article->title }}"
                class="pdf-embed"
                loading="lazy"></iframe>

            <p class="text-center text-muted small mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Previewing the published PDF. Use
                <a href="{{ route('article.download', $article->slug) }}" class="text-decoration-underline">Download PDF</a>
                to read or save it.
            </p>
        @endif
    </div>
</div>
@endsection
