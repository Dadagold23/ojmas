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
                    <div class="col-lg-5">
                        <label class="form-label fw-semibold">Search Box 2</label>
                        <input
                            type="text"
                            name="q"
                            class="form-control"
                            placeholder="Search name, issue no, volume no, journal topic, author..."
                            value="{{ $search ?? '' }}"
                        >
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Subject Area</label>
                        <select name="subject" class="form-select">
                            <option value="">All Subjects</option>
                            @foreach($subjects ?? [] as $item)
                                <option value="{{ $item }}" @selected(($subject ?? '') === $item)>{{ $item }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Year</label>
                        <select name="year" class="form-select">
                            <option value="">All Years</option>
                            @foreach($years ?? [] as $item)
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
                                <i class="bi bi-person-circle me-1"></i>
                                {{ $article->author->name ?? 'Unknown Author' }}
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
