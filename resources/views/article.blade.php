@extends('layouts.app')

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
                    <a href="{{ route('archives') }}" class="btn btn-outline-light">
                        <i class="bi bi-arrow-left me-2"></i>Back to Archives
                    </a>
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
                    @forelse($article->submission->articleAuthors ?? [] as $coAuthor)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="fw-semibold">{{ $coAuthor->full_name }}</div>
                        <div class="small text-muted">{{ $coAuthor->email ?? 'No email' }}</div>
                        <div class="small mt-1">{{ $coAuthor->institution ?? 'Institution not provided' }}</div>
                        <div class="small text-muted">{{ $coAuthor->country ?? '' }}</div>
                    </div>
                    @empty
                        <div class="text-muted">No co-authors listed.</div>
                    @endforelse
                </div>
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
@endsection
