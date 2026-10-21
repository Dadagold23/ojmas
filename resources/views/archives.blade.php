@extends('layouts.app')

@section('content')
<style>
    .public-hero{
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(13,110,253,.95), rgba(15,23,42,.92)),
            url('{{ asset('images/backgrounds/archive-hero.jpg') }}') center center / cover no-repeat;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }

    .section-title{
        font-weight: 800;
        color: #0f172a;
    }

    .archive-card{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
        transition: .3s ease;
    }

    .archive-card:hover{
        transform: translateY(-5px);
        box-shadow: 0 22px 45px rgba(15,23,42,.12);
    }

    .archive-meta{
        font-size: .92rem;
        color: #64748b;
    }

    .archive-badge{
        border-radius: 999px;
        padding: .55rem .9rem;
        font-size: .8rem;
    }

    .filter-card{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }
</style>

<div class="container py-4">
    <div class="card public-hero mb-5" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="fw-bold mb-3">Journal Archives</h1>
                    <p class="mb-0 text-white-50">
                        Explore published scholarly articles by article title, topic, issue number, volume number, author, and more.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-collection" style="font-size: 5rem; opacity: .18;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card filter-card mb-4" data-aos="fade-up">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('archives') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">Search the archive</label>
                        <input
                            type="text"
                            name="q"
                            class="form-control"
                            placeholder="Article title, author, issue theme, volume..."
                            value="{{ $search ?? '' }}"
                        >
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Volume</label>
                        <select name="volume" class="form-select">
                            <option value="">All Volumes</option>
                            @foreach($volumeList ?? [] as $item)
                                <option value="{{ $item->volume_no }}" @selected(($volume ?? '') == $item->volume_no)>
                                    Volume {{ $item->volume_no }} — {{ $item->title }} ({{ $item->year }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Year</label>
                        <select name="year" class="form-select">
                            <option value="">All Years</option>
                            @foreach($issueYearList ?? [] as $item)
                                <option value="{{ $item }}" @selected(($year ?? '') == $item)>{{ $item }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 d-flex gap-2">
                        <button class="btn btn-primary w-100">Filter</button>
                        <a href="{{ route('archives') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-end mb-3" data-aos="fade-up">
        <div>
            <h2 class="section-title mb-1">Volumes &amp; Issues</h2>
            <p class="text-muted mb-0">Published issues of the journal with their table of contents.</p>
        </div>
        <a href="{{ route('issues.index') }}" class="btn btn-outline-primary btn-sm">
            All issues <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    @include('ads.render', ['campaigns' => $archiveAds ?? collect(), 'placementKey' => 'archive_banner', 'placementLabel' => 'Advertisement'])

    @if(isset($issues) && $issues->count())
        <div class="row g-4 mb-5">
            @foreach($issues as $issue)
                <div class="col-md-6 col-xl-4" data-aos="fade-up">
                    <div class="card h-100 archive-card overflow-hidden">
                        @if($issue->cover_image_path)
                            <a href="{{ route('issue.show', $issue->id) }}">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($issue->cover_image_path) }}"
                                     class="w-100" style="height: 220px; object-fit: cover;"
                                     alt="Cover of {{ $issue->title }}">
                            </a>
                        @endif
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <span class="badge bg-dark archive-badge">Volume {{ $issue->volume?->volume_no }}</span>
                                <span class="badge bg-primary archive-badge">Issue {{ $issue->issue_no }}</span>
                                @if($issue->published_on)
                                    <span class="badge bg-light text-dark border archive-badge">
                                        {{ \Carbon\Carbon::parse($issue->published_on)->format('F Y') }}
                                    </span>
                                @endif
                            </div>

                            <h5 class="fw-bold mb-1">{{ $issue->title }}</h5>
                            @if($issue->volume?->title)
                                <div class="archive-meta mb-2">{{ $issue->volume->title }}</div>
                            @endif

                            <div class="archive-meta mb-3">
                                <i class="bi bi-file-earmark-text me-1"></i>
                                {{ $issue->articles->where('status', 'published')->count() }} published {{ \Illuminate\Support\Str::plural('article', $issue->articles->where('status', 'published')->count()) }}
                            </div>

                            <div class="d-flex gap-2">
                                <a href="{{ route('issue.show', $issue->id) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-journal-text me-1"></i>View issue
                                </a>
                                @if($issue->pdf_path)
                                    <a href="{{ route('issue.pdf', $issue->id) }}" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>Full-issue PDF
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="d-flex justify-content-center mb-5">{{ $issues->links() }}</div>
    @else
        <div class="alert alert-light border mb-5" data-aos="fade-up">
            <i class="bi bi-journals me-2"></i>No published issues match this filter yet.
        </div>
    @endif

    <div class="mb-4" data-aos="fade-up">
        <h2 class="section-title mb-2">Published Articles</h2>
        <p class="text-muted mb-0">Browse all articles available in the journal archive.</p>
    </div>

    @if(isset($articles) && $articles->count())
        <div class="row g-4">
            @foreach($articles as $article)
                <div class="col-md-6 col-xl-4" data-aos="fade-up">
                    <div class="card archive-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-primary-subtle text-primary archive-badge">Published</span>
                                @if(!empty($article->subject_area))
                                    <span class="badge bg-warning-subtle text-warning archive-badge">{{ $article->subject_area }}</span>
                                @endif
                            </div>

                            <h5 class="fw-bold mb-3">{{ $article->title }}</h5>

                            <p class="text-muted mb-3">
                                {{ \Illuminate\Support\Str::limit($article->abstract, 150) }}
                            </p>

                            <div class="archive-meta mb-2">
                                <i class="bi bi-people me-1"></i>
                                @php
                                    $archiveBylineNames = [$article->author->name ?? 'Unknown Author'];
                                    $archiveAuthorEmail = strtolower(trim((string) ($article->author->email ?? '')));
                                    foreach (($article->submission?->articleAuthors?->sortBy([['author_order','asc'],['id','asc']]) ?? collect()) as $archiveCoAuthor) {
                                        if (strtolower(trim((string) ($archiveCoAuthor->email ?? ''))) !== $archiveAuthorEmail) {
                                            $archiveBylineNames[] = $archiveCoAuthor->full_name;
                                        }
                                    }
                                @endphp
                                {{ implode(', ', $archiveBylineNames) }}
                            </div>

                            @if(!empty($article->issue))
                                <div class="archive-meta mb-2">
                                    <i class="bi bi-journal-bookmark me-1"></i>
                                    <a href="{{ route('issue.show', $article->issue->id) }}" class="text-decoration-none">
                                        {{ $article->issue->title }}
                                    </a>
                                    @if(!empty($article->issue->volume))
                                        - {{ $article->issue->volume->title }}
                                    @endif
                                </div>
                            @endif

                            <div class="archive-meta">
                                <i class="bi bi-calendar-event me-1"></i>
                                {{ $article->published_on ? \Carbon\Carbon::parse($article->published_on)->format('d M Y') : $article->created_at?->format('d M Y') }}
                            </div>
                        </div>

                        <div class="card-footer bg-white border-0 px-4 pb-4">
                            <a href="{{ route('article.show', $article->slug) }}" class="btn btn-outline-primary">
                                Read Article
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if(method_exists($articles, 'links'))
            <div class="mt-5">
                {{ $articles->links() }}
            </div>
        @endif
    @else
        <div class="alert alert-info shadow-sm">
            No published articles matched your search.
        </div>
    @endif
</div>
@endsection
