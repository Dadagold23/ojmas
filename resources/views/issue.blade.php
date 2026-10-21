@extends('layouts.app')

@section('page_title', ($issue->volume ? 'Volume '.$issue->volume->volume_no.', ' : '').'Issue '.$issue->issue_no.' — '.config('journal.title', config('app.name', 'OJMAS')))

@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($issue->description ?? ('Table of contents for'.$issue->title)), 155))

@section('head_extras')
    <link rel="canonical" href="{{ route('issue.show', $issue->id) }}">
@endsection

@section('content')
<style>
    .issue-hero {
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(30, 41, 59, .96), rgba(99, 102, 241, .90)),
            url('{{ asset('images/backgrounds/issue-hero.jpg') }}') center center / cover no-repeat;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15, 23, 42, .16);
    }

    .content-card,
    .meta-card {
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .08);
    }

    .section-title {
        font-weight: 800;
        color: #0f172a;
    }

    .article-item {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        transition: .25s ease;
        background: #fff;
    }

    .article-item:hover {
        border-color: #cbd5e1;
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(15, 23, 42, .06);
    }

    .meta-label {
        font-size: .82rem;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: .4px;
    }

    .meta-value {
        font-weight: 600;
        color: #0f172a;
    }
</style>

<div class="container py-4">
    {{-- Issue Hero --}}
    <div class="card issue-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-9">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-indigo text-white px-3 py-2">
                            {{ $issue->volume ? 'Volume '.$issue->volume->volume_no.' — '.$issue->volume->title : 'Journal Issue' }}
                        </span>
                        @if($issue->published_on)
                            <span class="badge bg-light text-dark px-3 py-2 border">
                                Published {{ \Carbon\Carbon::parse($issue->published_on)->format('j F Y') }}
                            </span>
                        @endif
                    </div>

                    <h1 class="fw-bold mb-3">{{ $issue->title }}</h1>

                    <p class="mb-0 text-white-50">
                        @if($issue->issue_no)
                            Issue Number: {{ $issue->issue_no }} |
                        @endif
                        Total Articles: {{ $tableOfContents->count() }}
                    </p>
                    @if($issue->description)
                        <p class="text-white-50 small mb-0 mt-2">{{ \Illuminate\Support\Str::limit($issue->description, 220) }}</p>
                    @endif
                </div>

                <div class="col-lg-3 text-lg-end mt-4 mt-lg-0">
                    @if($issue->cover_image_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($issue->cover_image_path) }}" alt="Cover of {{ $issue->title }}"
                             class="img-fluid rounded-4 mb-3 shadow-sm" style="max-height: 260px;">
                    @endif
                    @if($issue->pdf_path)
                        <div class="d-flex flex-column gap-2 align-items-lg-end">
                            <a href="{{ route('issue.pdf', $issue->id) }}" class="btn btn-warning fw-semibold">
                                <i class="bi bi-file-earmark-pdf me-2"></i>Download Full-Issue PDF
                            </a>
                        </div>
                    @endif
                    <div class="d-flex flex-column gap-2 align-items-lg-end mt-2">
                        <a href="{{ route('issues.index') }}" class="btn btn-outline-light">
                            <i class="bi bi-arrow-left me-2"></i>Back to Issues
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Main Content --}}
        <div class="col-lg-8">
            <div class="card content-card" data-aos="fade-up">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h4 class="fw-bold mb-0">Table of Contents</h4>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex flex-column gap-3">
                        @forelse($tableOfContents as $article)
                            <div class="article-item p-4">
                                <div class="d-flex align-items-baseline gap-2 mb-2">
                                    <span class="badge bg-dark text-white px-2 py-1">{{ $loop->iteration }}</span>
                                    <span class="badge text-bg-light border text-muted">{{ $article->subject_area ?? ($article->submission?->subject_area ? 'Article' : 'Article') }}</span>
                                </div>

                                <h5 class="fw-bold mb-2">
                                    <a href="{{ route('article.show', $article->slug) }}" class="text-decoration-none text-dark">
                                        {{ $article->title }}
                                    </a>
                                </h5>

                                <div class="small mb-2">
                                    @foreach($bylines->get($article->id, []) as $bylineAuthor)
                                        {{ $bylineAuthor['name'] }}{{ !$loop->last ? ($loop->remaining === 1 ? ' & ' : ', ') : '' }}
                                    @endforeach
                                </div>

                                @if($article->pages || $article->doi || $article->published_on)
                                    <div class="small text-muted mb-3">
                                        @if($article->pages)
                                            | Pages: {{ $article->pages }}
                                        @endif
                                        @if($article->issue->published_on)
                                            | Published: {{ \Carbon\Carbon::parse($article->published_on ?? $article->issue->published_on)->format('j F Y') }}
                                        @endif
                                        @if($article->doi)
                                            | <a href="https://doi.org/{{ $article->doi }}" target="_blank" rel="noopener">DOI: {{ $article->doi }}</a>
                                        @endif
                                    </div>
                                @endif

                                <p class="text-muted mb-3">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($article->abstract), 200) }}
                                </p>

                                <div class="d-flex gap-2">
                                    <a href="{{ route('article.show', $article->slug) }}" class="btn btn-sm btn-outline-primary">
                                        Read Article
                                    </a>
                                    <a href="{{ route('article.download', $article->slug) }}" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-download me-1"></i> Download PDF
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-1 d-block mb-3 opacity-25"></i>
                                No articles published in this issue yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar Meta --}}
        <div class="col-lg-4">
            <div class="card meta-card mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Issue Information</h5>
                </div>
                <div class="card-body p-4">
                    @if($issue->description)
                        <div class="mb-4">
                            <div class="meta-label">Description</div>
                            <div class="text-muted mt-1 small" style="line-height:1.7">{{ $issue->description }}</div>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-6">
                            <div class="meta-label">Published</div>
                            <div class="meta-value">{{ $issue->published_on ? \Carbon\Carbon::parse($issue->published_on)->format('d M Y') : 'Draft' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="meta-label">Volume</div>
                            <div class="meta-value">{{ $issue->volume ? $issue->volume->title : 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="meta-label">Issue Number</div>
                            <div class="meta-value">{{ $issue->issue_no }}</div>
                        </div>
                        <div class="col-6">
                            <div class="meta-label">Total Articles</div>
                            <div class="meta-value">{{ $tableOfContents->count() }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
