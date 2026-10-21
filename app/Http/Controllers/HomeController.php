<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\Article;
use App\Models\Issue;
use App\Models\Volume;
use App\Services\Publications\ArticleBylineService;
use App\Services\Publications\CitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;


class HomeController extends Controller
{
    public function index(Request $request)
    {
        $published = Article::with(['author', 'issue.volume'])
            ->where('status', 'published')
            ->latest('published_on')
            ->take(6)
            ->get();

        $latestIssues = Issue::with(['volume', 'articles'])
            ->latest('published_on')
            ->take(4)
            ->get();

        $featuredIssue = Issue::with(['volume', 'articles.author'])
            ->where('status', 'published')
            ->latest('published_on')
            ->first();

        // Addendum M2: the homepage's advertisement and announcement slots.
        // Rendering these records an impression per visitor per campaign per
        // day; nothing appears outside its recorded window.
        $adService = app(\App\Services\Advertising\AdCampaignService::class);
        $homepageAds = collect($adService->visibleForPlacement('homepage_banner', $request));
        $announcementAds = collect($adService->visibleForPlacement('announcement_strip', $request));

        $journalHighlights = [
            'publisher' => 'OJMAS',
            'office' => 'Offa, Kwara State, Nigeria',
            'email' => 'support@ojmaspublish.online',
            'phone' => '+234 913 494 3054',
            'website' => 'ojmaspublish.online',
            'license' => 'All rights reserved',
            'license_version' => 'Creative Commons Attribution 4.0 International License',
            'licensing_email' => 'editorial@openjournalsnigeria.org.ng',
            'licensing_phone' => '+234(0)9046008579',
            'licensing_website' => 'www.openjournalsnigeria.org.ng',
            'licensing_office' => 'Room No. 38, First Floor, Muhammadu Buhari Administrative Block, Edo University Iyamho, Edo State, Nigeria',
            'flyer' => 'images/journal/call-for-paper-2026-09.jpeg',
                'p_issn' => '3121-7508',
                'e_issn' => '3156-5182',
        ];

        $homeScope = [
            'Open-access, peer-reviewed multidisciplinary journal',
            'Interdisciplinary research in education, management, sciences, arts, technology, and social development',
            'A platform for researchers, practitioners, and policymakers solving complex global challenges',
        ];

        return view('welcome', compact('published', 'latestIssues', 'featuredIssue', 'journalHighlights', 'homeScope', 'homepageAds', 'announcementAds'));
    }

    public function archives(Request $request)
    {
        $search  = trim((string) $request->get('q'));
        $subject = trim((string) $request->get('subject'));
        $year    = trim((string) $request->get('year'));
        $volume  = trim((string) $request->get('volume'));

        // The archive's primary listing: published issues as organised units —
        // volume, year, theme, cover, article count and (where it exists) the
        // full-issue PDF. Only published material can ever appear.
        $issues = Issue::with(['volume', 'articles'])
            ->where('status', 'published')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('issue_no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('volume', fn ($vq) => $vq->where('title', 'like', "%{$search}%")
                            ->orWhere('volume_no', 'like', "%{$search}%")
                            ->orWhere('year', 'like', "%{$search}%"))
                        ->orWhereHas('articles', fn ($aq) => $aq->where('status', 'published')
                            ->where(fn ($w) => $w->where('title', 'like', "%{$search}%")
                                ->orWhere('abstract', 'like', "%{$search}%")
                                ->orWhere('keywords', 'like', "%{$search}%")));
                });
            })
            ->when($volume !== '' && $volume !== null, fn ($query) =>
                $query->whereHas('volume', fn ($vq) => $vq->where('volume_no', $volume)))
            ->when($year !== '' && $year !== null, fn ($query) =>
                $query->whereYear('published_on', $year))
            ->orderByDesc('published_on')
            ->paginate(12)
            ->withQueryString();

        $issueYearList = Issue::where('status', 'published')
            ->whereNotNull('published_on')
            ->get(['published_on'])
            ->map(fn ($issue) => substr((string) $issue->published_on, 0, 4))
            ->unique()
            ->sortDesc()
            ->values();

        $volumeList = \App\Models\Volume::where('status', 'published')
            ->orderByDesc('volume_no')
            ->get(['id', 'volume_no', 'title', 'year']);

        $articles = Article::with(['submission.articleAuthors', 'author', 'issue.volume'])
            ->where('status', 'published')
            ->whereHas('issue', function ($q) {
                $q->where('status', 'published');
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('abstract', 'like', "%{$search}%")
                        ->orWhere('keywords', 'like', "%{$search}%")
                        ->orWhereHas('author', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('institution', 'like', "%{$search}%");
                        })
                        ->orWhereHas('submission', function ($q) use ($search) {
                            $q->where('title', 'like', "%{$search}%")
                                ->orWhere('abstract', 'like', "%{$search}%")
                                ->orWhere('keywords', 'like', "%{$search}%")
                                ->orWhere('subject_area', 'like', "%{$search}%")
                                ->orWhere('submission_no', 'like', "%{$search}%")
                                ->orWhereHas('articleAuthors', function ($authorQuery) use ($search) {
                                    $authorQuery->where('full_name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%")
                                        ->orWhere('institution', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('issue', function ($q) use ($search) {
                            $q->where('title', 'like', "%{$search}%")
                                ->orWhere('issue_no', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%")
                                ->orWhereHas('volume', function ($volumeQuery) use ($search) {
                                    $volumeQuery->where('title', 'like', "%{$search}%")
                                        ->orWhere('volume_no', 'like', "%{$search}%")
                                        ->orWhere('year', 'like', "%{$search}%")
                                        ->orWhere('description', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($subject, function ($query) use ($subject) {
                $query->whereHas('submission', fn($q) => $q->where('subject_area', $subject));
            })
            ->when($year !== '' && $year !== null, function ($query) use ($year) {
                $query->whereYear('published_on', $year);
            })
            ->latest('published_on')
            ->paginate(12, ['*'], 'page_a')
            ->withQueryString();

        $subjects = \App\Models\Submission::whereHas('article', function ($q) {
                $q->where('status', 'published')
                  ->whereHas('issue', fn($iq) => $iq->where('status', 'published'));
            })
            ->whereNotNull('subject_area')
            ->where('subject_area', '!=', '')
            ->distinct()
            ->orderBy('subject_area')
            ->pluck('subject_area');

        return view('archives', compact(
            'articles', 'issues', 'subjects', 'issueYearList', 'volumeList',
            'search', 'subject', 'year', 'volume',
        ) + [
            // Addendum M2: the archive banner slot.
            'archiveAds' => collect(app(\App\Services\Advertising\AdCampaignService::class)
                ->visibleForPlacement('archive_banner', $request)),
        ]);
    }

    public function issues(Request $request)
    {
        $search = trim((string) $request->get('q'));
        $volume = trim((string) $request->get('volume'));
        $year = trim((string) $request->get('year'));

        $issues = Issue::with(['volume', 'articles.author', 'articles.submission.articleAuthors'])
            ->where('status', 'published')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('issue_no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('volume', function ($q) use ($search) {
                            $q->where('title', 'like', "%{$search}%")
                                ->orWhere('volume_no', 'like', "%{$search}%")
                                ->orWhere('year', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        })
                        ->orWhereHas('articles', function ($q) use ($search) {
                            $q->where('status', 'published')
                                ->where(function ($articleQuery) use ($search) {
                                    $articleQuery->where('title', 'like', "%{$search}%")
                                        ->orWhere('abstract', 'like', "%{$search}%")
                                        ->orWhere('keywords', 'like', "%{$search}%")
                                        ->orWhereHas('author', function ($authorQuery) use ($search) {
                                            $authorQuery->where('name', 'like', "%{$search}%")
                                                ->orWhere('email', 'like', "%{$search}%")
                                                ->orWhere('institution', 'like', "%{$search}%");
                                        })
                                        ->orWhereHas('submission', function ($submissionQuery) use ($search) {
                                            $submissionQuery->where('title', 'like', "%{$search}%")
                                                ->orWhere('abstract', 'like', "%{$search}%")
                                                ->orWhere('keywords', 'like', "%{$search}%")
                                                ->orWhere('subject_area', 'like', "%{$search}%")
                                                ->orWhere('submission_no', 'like', "%{$search}%")
                                                ->orWhereHas('articleAuthors', function ($coAuthorQuery) use ($search) {
                                                    $coAuthorQuery->where('full_name', 'like', "%{$search}%")
                                                        ->orWhere('email', 'like', "%{$search}%")
                                                        ->orWhere('institution', 'like', "%{$search}%");
                                                });
                                        });
                                });
                        });
                });
            })
            ->when($volume, function ($query) use ($volume) {
                $query->whereHas('volume', function ($q) use ($volume) {
                    $q->where('title', $volume);
                });
            })
            ->when($year, function ($query) use ($year) {
                $query->whereYear('published_on', $year);
            })
            ->latest('published_on')
            ->paginate(12)
            ->withQueryString();

        $volumes = Issue::with('volume')
            ->where('status', 'published')
            ->get()
            ->pluck('volume.title')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $years = Issue::where('status', 'published')
            ->whereNotNull('published_on')
            ->selectRaw('YEAR(published_on) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        return view('issues', compact('issues', 'volumes', 'years', 'search', 'volume', 'year'));
    }

    public function article(Request $request, string $slug)
    {
        $article = Article::with([
                'author',
                'issue.volume',
                'submission.articleAuthors',
                'publication',
            ])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $byline = app(\App\Services\Publications\ArticleBylineService::class)->for($article);
        $citations = app(\App\Services\Publications\CitationService::class)->for($article);

        return view('article', compact('article', 'byline', 'citations') + [
            // Addendum M2: the article page banner slot.
            'articleAds' => collect(app(\App\Services\Advertising\AdCampaignService::class)
                ->visibleForPlacement('article_banner', $request)),
        ]);
    }

    /**
     * Citation exports (apamla/chicago/bibtex/ris) as plain-text downloads,
     * built from exactly the same citation data the article page shows.
     */
    public function cite(string $slug, string $format)
    {
        abort_unless(in_array($format, ['apa', 'mla', 'chicago', 'bibtex', 'ris', 'txt'], true), 404);

        $article = Article::with(['issue.volume', 'submission.articleAuthors'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $citations = app(\App\Services\Publications\CitationService::class)->for($article);

        $content = $format === 'txt' ? $citations['apa'] : $citations[$format];

        $extension = $format === 'txt' ? 'txt' : $format;
        $contentType = match ($format) {
            'bibtex' => 'application/x-bibtex; charset=UTF-8',
            'ris' => 'application/x-research-info-systems; charset=UTF-8',
            default => 'text/plain; charset=UTF-8',
        };

        return response($content)
            ->withHeaders([
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="'.$this->citationFileName($article, $extension).'"',
            ]);
    }

    private function citationFileName(Article $article, string $extension): string
    {
        $base = \Illuminate\Support\Str::slug(substr($article->title, 0, 60));

        return 'ojmas-'.$base.'-'.substr((string) $article->slug, -5).'.'.$extension;
    }

    /**
     * XML sitemap for the public journal record: published issues, their
     * editions pages, published articles, and index pages. Draft material and
     * unpublished manuscripts can never appear.
     */
    public function sitemap()
    {
        $baseUrl = rtrim(config('app.url'), '/');

        $issues = \App\Models\Issue::with('volume')
            ->where('status', 'published')
            ->orderByDesc('published_on')
            ->get();

        $articles = \App\Models\Article::with('issue')
            ->where('status', 'published')
            ->whereHas('issue', fn ($q) => $q->where('status', 'published'))
            ->orderByDesc('published_on')
            ->get();

        $entries = [
            ['loc' => route('home'), 'priority' => '1.0', 'freq' => 'daily'],
            ['loc' => route('archives'), 'priority' => '0.9', 'freq' => 'daily'],
            ['loc' => route('issues.index'), 'priority' => '0.9', 'freq' => 'daily'],
        ];

        foreach ($issues as $issue) {
            $entries[] = [
                'loc' => route('issue.show', $issue->id),
                'priority' => '0.8',
                'freq' => 'monthly',
                'lastmod' => optional($issue->updated_at)->toAtomString(),
            ];
        }

        foreach ($articles as $article) {
            $entries[] = [
                'loc' => route('article.show', $article->slug),
                'priority' => '0.8',
                'freq' => 'yearly',
                'lastmod' => optional($article->updated_at)->toAtomString(),
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($entry['loc'])."</loc>\n";

            if (! empty($entry['lastmod'])) {
                $xml .= '    <lastmod>'.$entry['lastmod']."</lastmod>\n";
            }

            $xml .= '    <changefreq>'.$entry['freq']."</changefreq>\n";
            $xml .= '    <priority>'.$entry['priority']."</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function articlePdf(string $slug)
    {
        return $this->serveArticlePdf($slug);
    }

    public function downloadArticlePdf(string $slug)
    {
        return $this->serveArticlePdf($slug);
    }

    /**
     * Serves the article's real PDF when one has been published or uploaded,
     * so downloads match what the reader previews. Falls back to generating a
     * document from the stored metadata only when no real file exists.
     */
    protected function serveArticlePdf(string $slug)
    {
        $article = Article::with(['issue.volume', 'submission.articleAuthors', 'publication', 'submission.files'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $stored = $this->resolveStoredArticlePdf($article);

        if ($stored !== null) {
            return Storage::disk($stored['disk'])->response(
                $stored['path'],
                $stored['name'],
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment',
                ]
            );
        }

        $pdf = Pdf::loadView('articles.pdf', compact('article'))
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top', 28)
            ->setOption('margin-bottom', 28)
            ->setOption('margin-left', 28)
            ->setOption('margin-right', 28);

        $pdf->render();
        $output = $pdf->getDomPDF()->output();

        $fallback = preg_replace('/[^\w\.]+/', '', $article->slug ?? 'article');
        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fallback . '.pdf"',
            'Content-Length' => strlen($output),
        ]);
    }

    /**
     * Locates the article's real PDF: the published PDF first, then the
     * author's manuscript upload. Returns null when neither exists.
     *
     * @return array{disk: string, path: string, name: string}|null
     */
    protected function resolveStoredArticlePdf(Article $article): ?array
    {
        $isPdf = fn (?string $name): bool =>
            strtolower(pathinfo((string) $name, PATHINFO_EXTENSION)) === 'pdf';

        $publishedPath = $article->publication?->published_pdf_path;

        if ($publishedPath && $isPdf($publishedPath) && Storage::disk('public')->exists($publishedPath)) {
            return [
                'disk' => 'public',
                'path' => $publishedPath,
                'name' => ($article->title ?? 'article') . '.pdf',
            ];
        }

        $manuscript = $article->submission?->files?->first(
            fn ($file) => $isPdf($file->original_name) && $file->resolvedPrivatePath() !== null
        );

        if ($manuscript) {
            return [
                'disk' => 'private',
                'path' => $manuscript->resolvedPrivatePath(),
                'name' => $manuscript->original_name,
            ];
        }

        return null;
    }

    /**
     * Streams the article's real PDF inline so it can be previewed on the
     * article page.
     */
    public function articlePreview(string $slug)
    {
        $article = Article::with(['publication', 'submission.files'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $stored = $this->resolveStoredArticlePdf($article);

        abort_if($stored === null, 404);

        return Storage::disk($stored['disk'])->response(
            $stored['path'],
            $stored['name'],
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline',
            ]
        );
    }

    public function issue(Request $request, int $id)
    {
        $issue = Issue::with([
                'volume',
                'articles.author',
                'articles.submission.articleAuthors',
            ])
            ->where('status', 'published')
            ->findOrFail($id);

        // Approved editorial sequence; only published articles are presented,
        // and bylines are resolved once so the ToC shows the same byline as
        // the article page.
        $tableOfContents = $issue->orderedArticles()
            ->where('status', 'published')
            ->get()
            ->values();

        $bylineService = app(ArticleBylineService::class);
        $bylines = collect();
        foreach ($tableOfContents as $tocArticle) {
            $bylines[$tocArticle->id] = $bylineService->for($tocArticle);
        }

        return view('issue', compact('issue', 'tableOfContents', 'bylines') + [
            // Addendum M2: the issue page promotional slot.
            'issueAds' => collect(app(\App\Services\Advertising\AdCampaignService::class)
                ->visibleForPlacement('issue_promo', $request)),
        ]);
    }

    public function issuePdf(int $id)
    {
        $issue = Issue::with([
                'volume',
                'articles.author',
                'articles.publication',
            ])
            ->findOrFail($id);

        // 1. An uploaded compiled journal always wins.
        $compiled = $issue->pdf_path;

        if ($compiled && Storage::disk('public')->exists($compiled)) {
            return Storage::disk('public')->download($compiled, ($issue->title ?? 'issue') . '.pdf');
        }

        // 2. Otherwise serve the issue's original uploaded article content when
        //    there is exactly one, so the download is the real document.
        $uploaded = $issue->articles
            ->map(fn (Article $article) => $article->publication?->published_pdf_path)
            ->filter(fn ($path) => $path && Storage::disk('public')->exists($path))
            ->unique()
            ->values();

        if ($uploaded->count() === 1) {
            $only = $uploaded->first();

            return Storage::disk('public')->download(
                $only,
                (($issue->volume?->title ?? 'issue') . ' ' . ($issue->title ?? '')) . '.pdf'
            );
        }

        // 3. Fall back to a generated summary.
        $pdf = Pdf::loadView('issues.pdf', compact('issue'))
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top', 28)
            ->setOption('margin-bottom', 28)
            ->setOption('margin-left', 28)
            ->setOption('margin-right', 28);

        $pdf->render();
        $output = $pdf->getDomPDF()->output();
        $fallback = preg_replace('/[^\w\.]+/', '', \Illuminate\Support\Str::slug($issue->title));
        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fallback . '.pdf"',
            'Content-Length' => strlen($output),
        ]);
    }

    public function guidelines()
    {
        return view('guidelines');
    }

    public function editorial()
    {
        return view('journal.editorial', [
            'editorial' => [
                'title' => 'Editorial',
                'message' => [
                    'This first edition was produced through the commitment of reviewers, assessors, and editors who gave their time sacrificially at every stage of the publication process. Their work remains deeply valued and publicly acknowledged.',
                    'Special appreciation goes to the editorial advisers, the publisher, and the wider OJMAS team whose encouragement, technical support, and thoughtful guidance helped bring this edition to publication.',
                    'The journal also recognizes every contributing author and every person who supported the production of this academic document in one way or another. OJMAS is built on shared scholarly labour, and this edition reflects that collective effort.',
                ],
                'signatory' => [
                    'name' => 'Prof. MBS Jibrin',
                    'role' => 'Editor-In-Chief',
                    'department' => 'Department of English and Literary Studies, Faculty of Art',
                    'institution' => 'Prince Abubakar Audu University, Ayingba, Kogi State',
                ],
            ],
        ]);
    }

    public function editorialBoard()
    {
        return view('journal.editorial-board', [
            'title' => 'Editorial Board',
            'subtitle' => 'A multidisciplinary board bringing together expertise across education, agriculture, health sciences, communication, engineering, and the social sciences.',
            'members' => $this->editorialBoardMembers(),
        ]);
    }

    public function editorialAdvisers()
    {
        return view('journal.editorial-advisers', [
            'title' => 'Editorial Advisers',
            'subtitle' => 'Senior scholars and advisers supporting OJMAS with strategic academic guidance, interdisciplinary depth, and international perspective.',
            'members' => $this->editorialAdvisersList(),
        ]);
    }

    public function scopePolicies()
    {
        return view('journal.scope-policies', [
            'scopeAreas' => [
                'Education and educational development',
                'Management, business, and entrepreneurship studies',
                'Social sciences and humanities',
                'Arts, culture, and language studies',
                'Science, technology, agriculture, and health-related research',
            ],
            'policies' => [
                'OJMAS is a peer-reviewed, open-access international journal designed for interdisciplinary scholarship.',
                'Submitted works are presumed original, unpublished, and not under consideration elsewhere.',
                'Republishing any accepted work elsewhere requires prior permission from the Editor-In-Chief.',
                'Editorial assessment prioritizes originality, quality of presentation, contribution to knowledge, and relevance to current issues.',
            ],
            'guidelines' => [
                'Manuscripts should include a solid abstract or introduction and demonstrate quality contribution to knowledge.',
                'The title should not exceed 18 words and author details should appear on a separate cover page.',
                'The abstract should not exceed 150 words and should appear without author names on a separate sheet.',
                'Referencing should follow the latest APA style.',
                'Methodology should state the design, sample, instruments, and method of data gathering clearly.',
                'Results may be presented in tables but should include concise interpretation.',
            ],
            'submissionInfo' => [
                'Typed manuscripts are expected in double spacing on A4 paper.',
                'Original assessment and publication fees are determined by the Editorial Board.',
                'Submissions should be addressed to the Editor-In-Chief, OJMAS, Department of English and Literary Studies, Prince Abubakar Audu University, Ayingba, Kogi State.',
            ],
        ]);
    }

    public function callForPapers()
    {
        return view('journal.call-for-papers', [
            'call' => [
            'flyer' => 'images/journal/call-for-paper-2026-09.jpeg',
                'p_issn' => '3121-7508',
                'e_issn' => '3156-5182',
                'tagline' => 'Quarterly Journal',
                'intro' => 'OJMAS is a double-blind peer-reviewed quarterly journal committed to promoting knowledge across multiple academic disciplines and scholarly fields, proudly published by Philosophia Consulting.',
                'value' => 'The journal brings together professors and a wide range of PhD holders to encourage interdisciplinary collaboration, innovation, complex problem-solving, and global research efficiency.',
                'scope' => [
                    'Agriculture',
                    'Arts',
                    'Business and Economics',
                    'Education',
                    'Environmental and Earth Sciences',
                    'Management',
                    'Medical and Health Sciences',
                    'Science and Technology',
                    'Social Sciences and Humanities',
                ],
                'authorsGuidelines' => [
                    'The first page should contain author contact details including name, email, institution, and phone number.',
                    'Author names should begin with surname and be presented in bold.',
                    'Abstract should not exceed 250 words including keywords.',
                    'Use Times New Roman, 12pt font size, with double spacing.',
                    'The full manuscript should not exceed 14 pages; extra pages attract extra charges.',
                    'Use APA 7th edition referencing style.',
                ],
                'reviewPublication' => [
                    'All submissions undergo double-blind peer review.',
                    'Approved articles attract a N25,000 publication fee.',
                    'Articles are expected to be published by September 30, 2026.',
                ],
                'submissionGuidelines' => [
                    'Manuscripts must be original and not under consideration elsewhere.',
                    'Submission deadline is August 31, 2026.',
                    'Submit manuscripts through the journal website: https://www.ojmaspublish.online',
                    'A N10,000 vetting fee should accompany article submission.',
                ],
                'credentials' => [
                    'publisher' => 'OJMAS',
                    'website' => 'https://www.openjournalsnigeria.org.ng',
                    'email' => 'editorial@openjournalsnigeria.org.ng',
                    'phone' => '+234(0)9046008579',
                    'office' => 'Room No. 38, First Floor, Muhammadu Buhari Administrative Block, Edo University Iyamho, Edo State, Nigeria',
                    'license' => 'Creative Commons Attribution 4.0 International License',
                ],
                'ojmasCredentials' => [
                    'publisher' => 'OJMAS',
                    'website' => 'https://www.ojmaspublish.online',
                    'email' => 'support@ojmaspublish.online',
                    'phone' => '+234 913 494 3054',
                    'office' => 'Offa, Kwara State, Nigeria',
                ],
                'payment' => [
                    'vettingFee' => 'N10,000',
                    'accountNo' => '1221607465',
                    'bank' => 'Zenith Bank',
                    'accountName' => 'Philosophia Consulting',
                ],
                'contacts' => [
                    [
                        'name' => 'Dr. Olaoye Abdulmutolib Olajide',
                        'role' => '',
                        'email' => 'abdulmutolib.olaoye@fedpoffaonline.edu.ng',
                        'phone' => '+234 813 675 6844',
                    ],
                    [
                        'name' => 'Dr. Ikachukwu Otue Egbuta',
                        'role' => '',
                        'email' => 'egbutakachukwu@yahoo.com',
                        'phone' => '+234 808 837 8613',
                    ],
                    [
                        'name' => 'Mr. Omale Suraj Alladey',
                        'role' => 'Managing Editor',
                        'email' => 'osajplace@gmail.com',
                        'phone' => '+234 913 494 3054',
                    ],
                ],
            ],
        ]);
    }

    public function contact()
    {
        return view('contact');
    }

    protected function editorialBoardMembers(): array
    {
        return [
            [
                'name' => 'Prof. MBS Jibrin',
                'position' => 'Editor-In-Chief',
                'qualification' => 'Professor',
                'profession' => 'English and Literary Studies',
                'institution' => 'Prince Abubakar Audu University, Kogi State',
                'image' => 'images/journal/Chief, Prof MBS Jibrin.jpg',
                'profile' => "Professor Muhammed-Badar Salihu Jibrin is a distinguished scholar of English, born on 8th May, 1974 in Ajobe, Ojoku District of Ankpa, Kogi State, Nigeria, where he embarked on his life's journey and eventually rose to become a salient voice in text linguistics and stylistics in contemporary Nigeria.\n\nProfessor Jibrin's academic journey began at L.G.E.A. Ajobe, Ankpa, where he earned his First School Leaving Certificate in 1986, before proceeding to St. Charles' College, Ankpa, for his secondary education. His passion and quest for knowledge led him to Bayero University, Kano, where he obtained a Bachelor of Arts (Hons.) degree in English and Mass Communication in 1998, and a Master of Arts in English (Language) from the same institution in 2002. He later earned a Ph.D. in English (Language) from Ahmadu Bello University, Zaria, in 2012.\n\nSince joining Prince Abubakar Audu University, Anyigba, in 2002 as an Assistant Lecturer, Professor Jibrin has risen steadily through the ranks, attaining full Professorship in 2019. Over more than two decades, he has taught undergraduate and postgraduate courses spanning Advanced English Grammar, Contemporary English Usage, Stylistics, The English Language in Nigeria, Language and Society, Discourse Analysis, Research Methods, and Survey of English Literature.\n\nA devoted mentor and tutor, Professor Jibrin has supervised numerous undergraduate, master's, and doctoral dissertations, helping to shape a new generation of scholars. His research interests span stylistics, discourse analysis, pragmatics, and Nigerian English, with publications in books and reputable national and international journals. He was also a Fellow at the Centre for Advanced Research in English (CARE), University of Birmingham, United Kingdom, in 2012.\n\nBeyond teaching and research, Professor Jibrin has served in several administrative capacities, including Examination Officer, Deputy Director at the Centre for Pre-Degree and Diploma Studies, Provost of Kogi State College of Education, Ankpa, and Head of Department of English and Literary Studies at Prince Abubakar Audu University. He has also contributed to national assignments such as the Technical Aid Corps interview processes and Joint Admissions and Matriculation Board (JAMB), and is professionally affiliated with the Association of Nigerian Authors (ANA), the Linguistic Association of Nigeria (LAN), and the Pragmatics Association of Nigeria (PrAN).",
            ],
            [
                'name' => 'Dr. Olaoye Abdulmutolib Olajide',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Science Laboratory Technology',
                'institution' => 'Federal Polytechnic, Offa, Kwara State',
                'image' => 'images/journal/Dr. Abdulmutolib Olajide Olaoye.jpg',
                'profile' => 'Dr. Abdulmutolib Olajide Olaoye is a physicist specializing in Condensed Matter and Solid-State Physics, with strong research interests in advanced functional materials, nanomaterials, and energy-related technologies. He holds a PhD in Condensed Matter Physics from Universiti Sains Malaysia, an M.Tech in Solid State Physics, and a B.Tech in Physics with Telecommunication from the Federal University of Technology, Minna.',
            ],
            [
                'name' => 'Lt. Paul Tanko Tarfa',
                'position' => 'Board Member',
                'qualification' => 'Lieutenant Commander',
                'profession' => 'Pharmacy',
                'institution' => 'Nigerian Navy College of Health Sciences, Offa, Kwara State',
                'image' => 'images/journal/Lt Paul Tanko Tarfa.png',
                'profile' => 'Paul Tanko Tarfa is a pharmacist and Naval Officer. He graduated from the University of Maiduguri in 2011 and was commissioned into the Nigerian Navy in 2015. He also earned a Master of Science degree in Regulatory Science and a fellowship at the West African Postgraduate College of Pharmacists, with specialization in drug production and quality assurance. He has over a decade of experience in clinical pharmacy and currently serves as Head of Department, Pharmacy at the Nigerian Navy College of Health Sciences, Offa, where he trains and supervises pharmacy technician students. In his leisure time, Paul enjoys progressive discussions and playing golf.',
            ],
            [
                'name' => 'Dr. Sunday Ogheneruemu Asakpa',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Cybersecurity and Data Protection',
                'institution' => 'Federal Polytechnic, Offa, Kwara State',
            ],
            [
                'name' => 'Dr. Yusuf Ajani Sholola',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Mass Communication',
                'institution' => 'Summit University, Offa, Kwara State',
                'image' => 'images/journal/Dr. Sholola Yusuf Ajani.jpg',
                'profile' => 'Dr. Sholola Yusuf Ajani is a Nigerian academic and communications professional with a strong focus on media, research, and institutional development. With experience in higher education administration and scholarly engagement, he has contributed to advancing academic visibility, strategic communication, and programme development within the university system. His interests span media innovation, health communication, and the intersection of technology with education, particularly in emerging areas such as digital media and AI-driven communication strategies. He is also actively involved in organizing academic events, lectures, and public engagement initiatives that connect academia with industry and policy discourse. Sholola is recognized for his capacity to translate complex ideas into accessible narratives, making him effective in both academic writing and public communication. His work reflects a commitment to using media and research as tools for social impact, especially within underserved communities in Nigeria.',
            ],
            [
                'name' => 'Dr. Ikechukwu Otuu Egbuta',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Languages',
                'institution' => 'Federal Polytechnic, Offa, Kwara State',
            ],
            [
                'name' => 'Dr. John Agbo Ogbodo',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Forestry and Wildlife',
                'institution' => 'Nnamdi Azikiwe University, Awka',
                'image' => 'images/journal/Dr. John Agbo Ogbodo.jpg',
                'profile' => 'Dr. John Agbo Ogbodo is an academic in the Faculty of Agriculture, Department of Forestry and Wildlife at Nnamdi Azikiwe University, Awka. His scholarly profile is listed on the university staff profile portal.',
            ],
            [
                'name' => 'Dr. Hussain Adedayo Salaudeen',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Accountancy',
                'institution' => 'Federal Polytechnic, Offa, Kwara State',
                'image' => 'images/journal/Dr. Salaudeen.jpg',
                'profile' => 'Hussain Adedayo Salaudeen is a Senior Lecturer in the Department of Accountancy, Federal Polytechnic, Offa. He holds a B.Sc. degree in Accounting from Ahmadu Bello University, Zaria, and an M.Sc. in Accounting and PhD in Accounting from Obafemi Awolowo University, Ile-Ife. He is an Associate Member of the Institute of Chartered Accountants of Nigeria (ICAN), has published in refereed journals, and is also a reviewer for the Eurasian Journal of Management and Social Sciences (EJMSS), Tishk International University, Irbil.',
            ],
            [
                'name' => 'Dr. Enemadukwu Ameh',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Guidance and Counselling',
                'institution' => 'University of Calabar, Calabar',
                'image' => 'images/journal/Enemadukwu Ameh.jpg',
                'profile' => 'Enemadukwu Ameh holds a Bachelor of Arts degree in English and literary studies, PGDE, M.Ed in Guidance and Counselling, and Ph.D. in Guidance and Counselling (Rehabilitation Counselling). He is a Nigerian, a practicing counsellor, an educator, and a service delivery expert. Dr. Enemadukwu Ameh is a lecturer in the Department of Guidance and Counselling, Faculty of Educational Foundation Studies, University of Calabar, Nigeria. His research interests span rehabilitation, mental health, counselling, risk assessment and management, addiction, family-related variables, disaster management, artificial intelligence use in education, and general education. He has over 35 journal articles and book chapters published in reputable journals and is an active member of the Counselling Association of Nigeria.',
            ],
            [
                'name' => 'Dr. Muhammed Awwal Jatto',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Human Resource Management',
                'institution' => 'Federal Polytechnic, Offa, Kwara State',
                'image' => 'images/journal/Dr. Jatto.jpg',
            ],
            [
                'name' => 'Dr. Eyiene Ameh',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Educational Management',
                'institution' => 'Federal University Lokoja',
                'image' => 'images/journal/Dr. Eyiene Ameh.jpg',
                'profile' => 'Dr. Eyiene Ameh holds a PhD in Educational Management (Economics of Education) from the University of Calabar and is a Lecturer in the Department of Educational Foundations, Federal University Lokoja. He has published over 45 original articles in the field of Education and Educational Management in reputable journals and is a member of the Nigerian Association of Educational Administration and Planning.',
            ],
            [
                'name' => 'Dr. Chinedu Ositadimma Chukwu',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Educational Research, Statistics, Measurement and Evaluation',
                'institution' => 'University of Calabar, Calabar',
            ],
            [
                'name' => 'Engr. Dr. Sharafadeen Kunle Kolawole',
                'position' => 'Board Member',
                'qualification' => 'Engr., PhD',
                'profession' => 'Mechanical Engineering',
                'institution' => 'Federal Polytechnic, Offa, Kwara State',
                'image' => 'images/journal/Dr. Sharafadeen Kunle Kolawole.jpg',
                'profile' => 'Dr. Sharafadeen Kunle Kolawole is a researcher, senior faculty member, and academic in the Mechanical Engineering Department, School of Engineering and Technology, Federal Polytechnic, Offa, Kwara State, Nigeria. He has authored and co-authored several peer-reviewed publications in reputable international journals, including the Journal of Materials Science & Technology, JOM (Springer), the Journal of Materials Engineering and Performance, and Materials Technology (Taylor & Francis). He also serves as a reviewer to many international journals domiciled with Elsevier, Springer Nature, Taylor & Francis, and MDPI, among others.',
            ],
            [
                'name' => 'Dr. S. A. Abdulkadir',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Education',
                'institution' => 'University of Offa, Offa, Kwara State',
            ],
            [
                'name' => 'Dr. Kingsley Chigozie Akpala',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Dentistry',
                'institution' => 'Kwara State College of Health Sciences, Offa, Kwara State',
            ],
            [
                'name' => 'Dr. Ifunanya Charity Ogbodo',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Philosophy',
                'institution' => 'Nnamdi Azikiwe University, Awka',
            ],
            [
                'name' => 'Dr. T. A. Ishola',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Agricultural Science Education',
                'institution' => 'Lagos State University of Education',
            ],
            [
                'name' => 'Ter Richard Gesa',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Agricultural Technology',
                'institution' => 'Federal Polytechnic, Wannune, Benue State',
            ],
            [
                'name' => 'Dr. Uba Nwankwo',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Physics and Natural Sciences',
                'institution' => 'Alex Ekwueme Federal University, Ndufu-Alike, Ebonyi State',
            ],
            [
                'name' => 'Dr. M. M. Okoye',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Languages',
                'institution' => 'Federal Polytechnic, Offa, Kwara State',
            ],
            [
                'name' => 'Dr. Abdulmajeed Olatinwo',
                'position' => 'Board Member',
                'qualification' => 'PhD',
                'profession' => 'Social Sciences and Humanities',
                'institution' => 'Federal Polytechnic, Offa, Kwara State',
                'image' => 'images/journal/Dr. Abdulmajeed Olatinwo.jpg',
                'profile' => 'Dr. Abdulmajeed Olatinwo is a senior lecturer at the Department of Social Sciences and Humanities, The Federal Polytechnic Offa, Kwara State. He specializes in development economics. His academic qualifications include a B.Sc. in Economics from Usmanu Danfodiyo University, Sokoto; an M.Sc. in Economics, Banking and Finance from Heriot-Watt University, Edinburgh, UK; an M.Sc. in Economics from the University of Ilorin; and a PhD in Economics from Kwara State University, Malete.',
            ],
        ];
    }

    protected function editorialAdvisersList(): array
    {
        return [
            [
                'name' => 'Prof. Chijioke Uwasomba',
                'position' => 'Editorial Adviser',
                'qualification' => 'Professor',
                'profession' => 'English',
                'institution' => 'Obafemi Awolowo University, Ile-Ife, Osun State',
                'image' => 'images/journal/Chijioke Uwasomba.jpg',
                'profile' => 'Chijioke Uwasomba is Professor of English and Cultural Production at the Department of English, Obafemi Awolowo University, Ile-Ife, Nigeria.',
            ],
            [
                'name' => 'Prof. Abdul Adamu',
                'position' => 'Editorial Adviser',
                'qualification' => 'Professor',
                'profession' => 'Business Administration and Management',
                'institution' => 'Nasarawa State University, Keffi, Nasarawa State',
            ],
            [
                'name' => 'Prof. Ikechukwu Emmanuel Asika',
                'position' => 'Editorial Adviser',
                'qualification' => 'Professor',
                'profession' => 'English and Literary Studies',
                'institution' => 'Chukwuemeka Odumegwu Ojukwu University, Igbariam, Anambra State',
            ],
            [
                'name' => 'Prof. Dorothy Patience Ani-Ugodu',
                'position' => 'Editorial Adviser',
                'qualification' => 'Professor',
                'profession' => 'Agricultural Economics',
                'institution' => 'Joseph Sarwuan Tarka University, Makurdi, Benue State',
                'image' => 'images/journal/Prof. Dorothy Patience Ani-Ugodu.jpeg',
                'profile' => 'Dr. Dorothy Patience Ani is a distinguished Agricultural Economist with specialization in Agricultural Marketing and Agribusiness, Farm Management and Production Economics, and Sustainable Development. She has acquired knowledge and technical expertise through capacity-building trainings offered by organizations such as IFPRI, USAID/ADANS, PILAF, GIAE, and the World Bank. She has been actively involved in community development programs and project implementation with local and international donor organizations, focusing on agricultural productivity, environmental sustainability, and support for vulnerable and rural populations. She is an Associate Professor in the Department of Agricultural Economics and Extension at Joseph Sarwuan Tarka University, Makurdi, a facilitator in Social Standards at the Institute for Innovation in Procurement, Environmental and Social Standards, and a prolific scholar and journal reviewer.',
            ],
            [
                'name' => 'Prof. Ali Aminu',
                'position' => 'Editorial Adviser',
                'qualification' => 'Professor',
                'profession' => 'Agronomy',
                'institution' => 'Joseph Sarwuan Tarka University, Makurdi, Benue State',
            ],
            [
                'name' => 'Mohammed Umar',
                'position' => 'Editorial Adviser',
                'qualification' => 'Assistant Professor',
                'profession' => 'Accounting',
                'institution' => 'UAE University, United Arab Emirates',
            ],
            [
                'name' => 'Barr. Lukman Raji',
                'position' => 'Legal Adviser',
                'qualification' => 'Barrister',
                'profession' => 'Social Sciences and Humanities',
                'institution' => 'Federal Polytechnic, Offa, Kwara State',
            ],
            [
                'name' => 'Mr. Omale S. A.',
                'position' => 'Managing Editor',
                'qualification' => 'Mr.',
                'profession' => 'Languages',
                'institution' => 'Federal Polytechnic, Offa, Kwara State',
            ],
        ];
    }
}
