<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $article->title }}</title>
    <style>
        body{
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 13px;
            color: #1e293b;
            line-height: 1.7;
            margin: 28px;
        }

        .header{
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 12px;
            margin-bottom: 22px;
        }

        .journal{
            font-size: 14px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .title{
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .meta{
            font-size: 12px;
            color: #475569;
            margin-bottom: 4px;
        }

        .section-title{
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 22px;
            margin-bottom: 10px;
        }

        .box{
            border: 1px solid #e2e8f0;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="journal">OJMAS</div>
        <div class="title">{{ $article->title }}</div>
        <div class="meta">Author: {{ $article->author->name ?? 'Unknown Author' }}</div>
        <div class="meta">Published: {{ $article->published_at?->format('d M Y') ?? '-' }}</div>
        @if(!empty($article->issue))
            <div class="meta">Issue: {{ $article->issue->title }}</div>
        @endif
        @if(!empty($article->issue?->volume))
            <div class="meta">Volume: {{ $article->issue->volume->title }}</div>
        @endif
    </div>

    <div class="section-title">Abstract</div>
    <div class="box">
        {!! nl2br(e($article->abstract ?? '')) !!}
    </div>

    @if(!empty($article->keywords))
        <div class="section-title">Keywords</div>
        <div class="box">{{ $article->keywords }}</div>
    @endif

    @if(!empty($article->full_text))
        <div class="section-title">Full Text</div>
        <div>
            {!! nl2br(e(strip_tags($article->full_text))) !!}
        </div>
    @endif

    @if(($article->submission->articleAuthors ?? collect())->count())
        <div class="section-title">Co-Authors</div>
        @foreach($article->submission->articleAuthors as $coAuthor)
            <div class="box">
                <strong>{{ $coAuthor->full_name }}</strong><br>
                {{ $coAuthor->institution ?? '' }}<br>
                {{ $coAuthor->email ?? '' }}<br>
                {{ $coAuthor->country ?? '' }}
            </div>
        @endforeach
    @endif
</body>
</html>
5. Revamp resources/views/article.blade.php
Replace it with this upgraded version:
Blade
Copy code
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
    .meta-card,
    .citation-card{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
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

    .citation-box{
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem;
        font-size: .95rem;
        color: #334155;
    }

    .coauthor-card{
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem;
        background: #fff;
    }
</style>

@php
    $publishedYear = $article->published_at?->format('Y') ?? date('Y');
    $authorName = $article->author->name ?? 'Unknown Author';
    $citationApa = $authorName . ' (' . $publishedYear . '). ' . $article->title . '. OJMAS.';
    $citationMla = $authorName . '. "' . $article->title . '." OJMAS, ' . $publishedYear . '.';
@endphp

<div class="container py-4">
    <div class="card article-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-9">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-light text-primary px-3 py-2">Published Article</span>
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

            @if(!empty($article->full_text))
                <div class="card content-card mt-4" data-aos="fade-up" data-aos-delay="150">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <h5 class="fw-bold mb-3">Full Text</h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="article-content">
                            {!! nl2br(e(strip_tags($article->full_text))) !!}
                        </div>
                    </div>
                </div>
            @endif

            <div class="card citation-card mt-4" data-aos="fade-up" data-aos-delay="200">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-3">How to Cite</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3">
                        <div class="fw-semibold mb-2">APA</div>
                        <div class="citation-box">{{ $citationApa }}</div>
                    </div>
                    <div>
                        <div class="fw-semibold mb-2">MLA</div>
                        <div class="citation-box">{{ $citationMla }}</div>
                    </div>
                </div>
            </div>
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
                        <div class="meta-value">{{ $article->published_at?->format('d M Y') ?? '-' }}</div>
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
                        <div class="coauthor-card mb-3">
                            <div class="fw-semibold">{{ $coAuthor->full_name }}</div>
                            <div class="small text-muted">{{ $coAuthor->email ?? 'No email' }}</div>
                            <div class="small mt-1">{{ $coAuthor->institution ?? 'Institution not provided' }}</div>
                            <div class="small text-muted">{{ $coAuthor->country ?? '' }}</div>
                        </div>
                    @empty
                        <div class="text-muted">No co-authors listed.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
