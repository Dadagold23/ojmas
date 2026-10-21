@extends('layouts.app')

@section('hide_chrome', '')

@section('page_title', $article->title.' — '.config('journal.title', config('app.name', 'OJMAS')))

@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($article->abstract ?? ''), 155))

@section('head_extras')
    <link rel="canonical" href="{{ route('article.show', $article->slug) }}">

    {{-- Google Scholar-compatible citation meta tags, emitted only from
         verified publication metadata. --}}
    <meta name="citation_title" content="{{ $article->title }}">
    @foreach($byline as $author)
        <meta name="citation_author" content="{{ $author['name'] }}">
    @endforeach
    @if($article->published_on)
        <meta name="citation_publication_date" content="{{ $article->published_on->toDateString() }}">
    @elseif($article->issue?->published_on)
        <meta name="citation_publication_date" content="{{ $article->issue->published_on->toDateString() }}">
    @endif
    <meta name="citation_journal_title" content="{{ config('journal.title', config('app.name', 'OJMAS')) }}">
    @if($article->issue?->volume?->volume_no)
        <meta name="citation_volume" content="{{ $article->issue->volume->volume_no }}">
    @endif
    @if($article->issue?->issue_no)
        <meta name="citation_issue" content="{{ $article->issue->issue_no }}">
    @endif
    @if($article->pages && preg_match('/^\s*(\d+)\s*[-–]\s*(\d+)\s*$/', $article->pages, $rangeMatch))
        <meta name="citation_firstpage" content="{{ $rangeMatch[1] }}">
        <meta name="citation_lastpage" content="{{ $rangeMatch[2] }}">
    @endif
    @if($article->doi)
        <meta name="citation_doi" content="{{ $article->doi }}">
        <meta name="citation_abstract_html_url" content="{{ route('article.show', $article->slug) }}">
    @endif
    <meta name="citation_pdf_url" content="{{ route('article.download', $article->slug) }}">

    <script type="application/ld+json">
{{ json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'ScholarlyArticle',
    'headline' => $article->title,
    'abstract' => \Illuminate\Support\Str::limit(strip_tags((string) $article->abstract), 5000),
    'datePublished' => ($article->published_on ?? $article->issue?->published_on)?->toDateString(),
    'author' => collect($byline)->map(fn (array $author) => array_filter([
        '@type' => 'Person',
        'name' => $author['name'],
        'affiliation' => $author['institution'] ? ['@type' => 'Organization', 'name' => $author['institution']] : null,
        'sameAs' => $author['orcid'] ? 'https://orcid.org/'.$author['orcid'] : null,
    ]))->values()->all(),
    'isPartOf' => [
        '@type' => 'PublicationIssue',
        'issueNumber' => $article->issue?->issue_no,
        'isPartOf' => [
            '@type' => 'PublicationVolume',
            'volumeNumber' => $article->issue?->volume?->volume_no,
            'name' => config('journal.title', config('app.name', 'OJMAS')),
        ],
    ],
    'identifier' => $article->doi ? ['@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => $article->doi] : null,
    'url' => route('article.show', $article->slug),
    'license' => $article->licence,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}
    </script>
@endsection

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

                    <p class="mb-2">
                        @foreach($byline as $bylineAuthor)
                            <span class="fw-semibold">{{ $bylineAuthor['name'] }}</span>@if($bylineAuthor['is_corresponding'])<sup>*</sup>@endif
                            @if(!$loop->last){{ $loop->remaining === 1 ? ' & ' : ', ' }}@endif
                        @endforeach
                        @if(collect($byline)->contains(fn ($a) => $a['is_corresponding']))
                            <sup>*</sup><span class="small text-white-50 ms-1">corresponding author</span>
                        @endif
                    </p>

                    <p class="mb-0 text-white-50">
                        @if($article->issue?->volume)
                            {{ config('journal.title', 'OJMAS Journal') }} ·
                            Volume {{ $article->issue->volume->volume_no }}@if($article->issue->issue_no!==null), Issue {{ $article->issue->issue_no }}@endif
                            @if($article->issue->published_on) · {{ $article->issue->published_on->format('F Y') }}@endif
                        @else
                            Scholarly article published on OJMAS.
                        @endif
                    </p>
                    @if($article->doi)
                        <p class="mb-0 mt-2">
                            <a href="https://doi.org/{{ $article->doi }}" class="link-light text-decoration-underline" target="_blank" rel="noopener">
                                https://doi.org/{{ $article->doi }}
                            </a>
                        </p>
                    @endif
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
            @include('ads.render', ['campaigns' => $articleAds ?? collect(), 'placementKey' => 'article_banner', 'placementLabel' => 'Advertisement'])

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

            {{-- Citation formats: rendered from verified metadata only; every
                 field the record lacks is stated rather than invented. --}}
            <div class="card content-card mt-4" id="cite" data-aos="fade-up" data-aos-delay="150">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-3"><i class="bi bi-quote me-1"></i>Cite this article</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    @if(!empty($citations['limitations']))
                        <div class="alert alert-warning py-2 small rounded-3 mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            The official record appears to be missing some bibliographic details, so the citations are incomplete in these respects:
                            <ul class="mb-0 mt-1">
                                @foreach($citations['limitations'] as $limitation)
                                    <li>{{ $limitation }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <label class="meta-label mb-1" for="citation-apa">APA 7 (select all and copy)</label>
                    <textarea id="citation-apa" readonly rows="4" class="form-select-like w-100 border rounded-3 p-2 mb-3 bg-light" style="font-size:.9rem; resize:vertical;">{{ $citations['apa'] }}</textarea>

                    <details class="mb-2">
                        <summary class="fw-semibold small">More citation formats</summary>
                        <label class="meta-label mb-1 mt-2" for="citation-mla">MLA</label>
                        <textarea id="citation-mla" readonly rows="4" class="w-100 border rounded-3 p-2 mb-3 bg-light" style="font-size:.9rem; resize:vertical;">{{ $citations['mla'] }}</textarea>

                        <label class="meta-label mb-1" for="citation-chicago">Chicago</label>
                        <textarea id="citation-chicago" readonly rows="4" class="w-100 border rounded-3 p-2 mb-3 bg-light" style="font-size:.9rem; resize:vertical;">{{ $citations['chicago'] }}</textarea>
                    </details>

                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <a href="{{ route('article.cite', [$article->slug, 'bibtex']) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-filetype-bib me-1"></i>BibTeX
                        </a>
                        <a href="{{ route('article.cite', [$article->slug, 'ris']) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-file-earmark-arrow-down me-1"></i>RIS
                        </a>
                        <a href="{{ route('article.cite', [$article->slug, 'txt']) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-filetype-txt me-1"></i>Plain text citation
                        </a>
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
                        <div class="meta-label">Author list</div>
                        <div class="meta-value">
                            @foreach($byline as $bylineAuthor)
                                {{ $bylineAuthor['name'] }}@if($bylineAuthor['is_corresponding']) <span class="small">(corresponding)</span>@endif
                                {{ !$loop->last ? ($loop->remaining === 1 ? ' & ' : ', ') : '' }}
                            @endforeach
                        </div>
                    </div>

                    @if(!empty($article->subject_area))
                        <div class="mb-3">
                            <div class="meta-label">Subject Area</div>
                            <div class="meta-value">{{ $article->subject_area }}</div>
                        </div>
                    @endif

                    @if($article->pages)
                        <div class="mb-3">
                            <div class="meta-label">Pages</div>
                            <div class="meta-value">{{ $article->pages }}</div>
                        </div>
                    @endif

                    @if($article->doi)
                        <div class="mb-3">
                            <div class="meta-label">DOI</div>
                            <div class="meta-value">
                                <a href="https://doi.org/{{ $article->doi }}" class="text-decoration-none" target="_blank" rel="noopener">
                                    https://doi.org/{{ $article->doi }}
                                </a>
                            </div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <div class="meta-label">Published Date</div>
                        <div class="meta-value">{{ ($article->published_on ?? $article->issue?->published_on)?->format('d M Y') ?? $article->created_at?->format('d M Y') }}</div>
                    </div>

                    @if($article->licence || $article->copyright_note)
                        <div class="mb-3">
                            <div class="meta-label">Licence</div>
                            <div class="meta-value">
                                {{ $article->licence ?? '' }}@if($article->licence && $article->copyright_note) · @endif{{ $article->copyright_note ?? '' }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card meta-card mt-4" data-aos="fade-left" data-aos-delay="100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-3">Authors and Affiliations</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    @forelse($byline as $index => $bylineAuthor)
                        <div class="border rounded-4 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="fw-semibold">{{ $index + 1 }}. {{ $bylineAuthor['name'] }}</div>
                                @if($bylineAuthor['is_corresponding'])
                                    <span class="badge bg-primary-subtle text-primary text-uppercase">Corresponding</span>
                                @endif
                            </div>

                            @if($bylineAuthor['institution'])
                                <div class="small mt-1">{{ $bylineAuthor['institution'] }}@if($bylineAuthor['country']) · {{ $bylineAuthor['country'] }}@endif</div>
                            @elseif($bylineAuthor['country'])
                                <div class="small mt-1">{{ $bylineAuthor['country'] }}</div>
                            @endif

                            @if($bylineAuthor['email'])
                                <div class="small text-muted">{{ $bylineAuthor['email'] }}</div>
                            @endif

                            @if($bylineAuthor['orcid'])
                                <div class="small mt-1">
                                    <a href="https://orcid.org/{{ $bylineAuthor['orcid'] }}" class="text-decoration-none" target="_blank" rel="noopener">
                                        <i class="bi bi-fingerprint me-1"></i>ORCID: {{ $bylineAuthor['orcid'] }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">Author names are not on record for this article yet.</div>
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
                    <div class="pdf-meta"><strong>Authors:</strong>
                        @foreach($byline as $bylineAuthor)
                            {{ $bylineAuthor['name'] }}
                            {{ !$loop->last ? ($loop->remaining === 1 ? ' & ' : ', ') : '' }}
                        @endforeach
                    </div>
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
