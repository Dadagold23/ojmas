<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'OJMAS') }}</title>
    <meta name="description"
        content="OJMAS is a modern academic journal platform for article submission, peer review, publication, archives, and scholarly communication.">
    <meta name="keywords"
        content="journal, academic journal, research publication, peer review, scholarly articles, OJMAS">
    <meta name="author" content="OJMAS">

    <!-- Open Graph / Social Metadata -->
    <meta property="og:title" content="{{ config('app.name', 'OJMAS') }}">
    <meta property="og:description"
        content="Publish, review, and access scholarly articles with ease on OJMAS.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/meta/ojmas-cover.png') }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ config('app.name', 'OJMAS') }}">
    <meta name="twitter:description"
        content="A modern journal platform for academic publishing, peer review, and archives.">
    <meta name="twitter:image" content="{{ asset('images/meta/ojmas-cover.jpg') }}">

    <link rel="icon" href="{{ asset('images/favicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <style>
    :root {
        --oj-primary: #0d6efd;
        --oj-dark: #0f172a;
        --oj-accent: #f59e0b;
        --oj-light: #f8fafc;
        --oj-muted: #64748b;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        background: #ffffff;
        color: #1e293b;
    }

    .navbar {
        background: rgba(15, 23, 42, 0.88) !important;
        backdrop-filter: blur(10px);
    }

    .navbar-brand {
        font-weight: 700;
        letter-spacing: .4px;
        max-width: calc(100% - 72px);
        line-height: 1.1;
        white-space: normal;
    }

    .hero-section {
        min-height: 92vh;
        display: flex;
        align-items: center;
        position: relative;
        overflow: hidden;
        padding-top: 6.5rem;
        padding-bottom: 2.5rem;
        background: linear-gradient(rgba(15, 23, 42, .78), rgba(15, 23, 42, .84)),
        url('{{ asset('images/backgrounds/hero-journal.jpg') }}') center center / cover no-repeat;
    }

    .hero-section::before {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(13, 110, 253, .25), transparent 28%),
            radial-gradient(circle at bottom left, rgba(245, 158, 11, .18), transparent 30%);
        pointer-events: none;
    }

    .hero-content {
        position: relative;
        z-index: 2;
    }

    .hero-badge {
        display: inline-block;
        background: rgba(255, 255, 255, .12);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .18);
        padding: .55rem 1rem;
        border-radius: 999px;
        font-size: .9rem;
        margin-bottom: 1.25rem;
    }

    .hero-title {
        font-size: clamp(2.3rem, 5vw, 4.6rem);
        line-height: 1.1;
        font-weight: 800;
        color: #fff;
    }

    .hero-text {
        font-size: 1.08rem;
        color: rgba(255, 255, 255, .85);
        max-width: 680px;
    }

    .hero-card {
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .14);
        backdrop-filter: blur(10px);
        border-radius: 1.25rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
        color: #fff;
    }

    .section-title {
        font-weight: 800;
        color: var(--oj-dark);
    }

    .section-subtitle {
        color: var(--oj-muted);
        max-width: 760px;
    }

    .feature-card,
    .article-card,
    .about-card,
    .contact-card,
    .stat-card {
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 12px 35px rgba(15, 23, 42, .08);
        transition: all .3s ease;
    }

    .feature-card:hover,
    .article-card:hover,
    .about-card:hover,
    .contact-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 18px 40px rgba(15, 23, 42, .12);
    }

    .feature-icon {
        width: 64px;
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        background: rgba(13, 110, 253, .08);
        color: var(--oj-primary);
        font-size: 1.6rem;
    }

    .stats-section {
        background: linear-gradient(135deg, #eff6ff, #f8fafc);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        color: var(--oj-dark);
    }

    .about-section {
        background: linear-gradient(rgba(248, 250, 252, .94), rgba(248, 250, 252, .94)),
        url('{{ asset('images/backgrounds/about-journal.jpg') }}') center center / cover no-repeat;
    }

    .cta-section {
        background: linear-gradient(rgba(15, 23, 42, .9), rgba(15, 23, 42, .9)),
        url('{{ asset('images/backgrounds/cta-library.jpg') }}') center center / cover no-repeat;
        color: #fff;
    }

    .contact-info i {
        color: var(--oj-primary);
        margin-right: .5rem;
    }

    .footer {
        background: #0f172a;
        color: rgba(255, 255, 255, .8);
    }

    .footer a {
        color: rgba(255, 255, 255, .85);
        text-decoration: none;
    }

    .footer a:hover {
        color: #fff;
    }

    .article-card .card-title {
        color: var(--oj-dark);
        font-weight: 700;
    }

    .btn-warning {
        color: #1f2937;
        font-weight: 600;
    }

    .text-accent {
        color: var(--oj-accent);
    }

    .info-strip-card {
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .08);
        overflow: hidden;
    }

    .scope-chip {
        display: inline-flex;
        align-items: center;
        padding: .55rem .9rem;
        border-radius: 999px;
        background: #eff6ff;
        color: var(--oj-primary);
        font-weight: 700;
        font-size: .9rem;
    }

    @media (max-width: 991.98px) {
        .navbar {
            padding-top: .85rem !important;
            padding-bottom: .85rem !important;
        }

        .navbar-collapse {
            margin-top: .85rem;
            padding: 1rem;
            border-radius: 1rem;
            background: rgba(15, 23, 42, .96);
        }

        .hero-section {
            min-height: auto;
            align-items: flex-start;
            padding-top: 7.5rem;
        }
    }

    @media (max-width: 575.98px) {
        .navbar-brand {
            font-size: .95rem;
            max-width: calc(100% - 60px);
        }

        .hero-badge {
            font-size: .78rem;
            padding: .45rem .8rem;
        }

        .hero-title {
            font-size: clamp(1.85rem, 9vw, 2.5rem);
        }

        .hero-text {
            font-size: 1rem;
        }

        .hero-section {
            padding-top: 8.25rem;
            padding-bottom: 2rem;
        }
    }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top py-3">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <i class="bi bi-journal-richtext me-2"></i>OJMAS
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ojmasNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="ojmasNavbar">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('archives') }}">Archives</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Journal</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('editorial') }}">Editorial</a></li>
                            <li><a class="dropdown-item" href="{{ route('editorial.board') }}">Editorial Board</a></li>
                            <li><a class="dropdown-item" href="{{ route('editorial.advisers') }}">Editorial Advisers</a>
                            </li>
                            <li><a class="dropdown-item" href="{{ route('scope.policies') }}">Scope & Policies</a></li>
                            <li><a class="dropdown-item" href="{{ route('call.for.papers') }}">Call for Papers</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('issues.index') }}">Issues</a></li>
                </ul>

                <div class="d-flex gap-2">
                    @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-outline-light btn-sm px-3">Dashboard</a>
                    @else
                    <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm px-3">Login</a>
                    @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn btn-warning btn-sm px-3">Register</a>
                    @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container hero-content">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <span class="hero-badge" data-aos="fade-up">P-ISSN: {{ $journalHighlights['p_issn'] }} • <a href="{{ route('archives') }}" class="text-white text-decoration-underline">E-ISSN: {{ $journalHighlights['e_issn'] }}</a> • Quarterly Journal</span>
                    <h1 class="hero-title mb-4" data-aos="fade-up" data-aos-delay="100">
                        Offa Journal of Multidisciplinary and Advanced Studies
                    </h1>

                    <p class="hero-text mb-4" data-aos="fade-up" data-aos-delay="200">
                        OJMAS is an open-access, peer-reviewed journal built for interdisciplinary scholarship,
                        editorial rigor, and a strong publishing experience for authors, reviewers, editors, and
                        readers.
                    </p>

                    <div class="d-flex flex-wrap gap-3" data-aos="fade-up" data-aos-delay="300">
                        <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-upload me-2"></i>Submit Article
                        </a>
                        <a href="{{ route('call.for.papers') }}" class="btn btn-warning btn-lg px-4">
                            <i class="bi bi-megaphone me-2"></i>Call for Papers
                        </a>
                        <a href="{{ route('archives') }}" class="btn btn-outline-light btn-lg px-4">
                            <i class="bi bi-collection me-2"></i>Browse Archives
                        </a>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card hero-card p-4" data-aos="zoom-in" data-aos-delay="250">
                        <div class="card-body">
                            <h4 class="fw-bold mb-4">Journal Snapshot</h4>
                            <div class="d-flex mb-3">
                                <div class="me-3 text-accent"><i class="bi bi-check-circle-fill"></i></div>
                                <div><strong>Email:</strong> {{ $journalHighlights['email'] }}</div>
                            </div>

                            <div class="d-flex mb-3">
                                <div class="me-3 text-accent"><i class="bi bi-check-circle-fill"></i></div>
                                <div><strong>Website:</strong> {{ $journalHighlights['website'] }}</div>
                            </div>

                            <div class="d-flex mb-3">
                                <div class="me-3 text-accent"><i class="bi bi-check-circle-fill"></i></div>
                                <div><strong>License Version:</strong> {{ $journalHighlights['license_version'] }}</div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="card info-strip-card" data-aos="fade-up">
                <div class="card-body p-4 p-lg-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-7">
                            <h2 class="section-title mb-3">Built for cross-disciplinary research and practical impact
                            </h2>
                            <p class="section-subtitle mb-4">
                                OJMAS provides a platform for researchers, practitioners, and policymakers to share
                                innovative ideas, rigorous findings, and practical solutions to emerging societal,
                                technological, and scientific challenges.
                            </p>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($homeScope as $item)
                                <span class="scope-chip">{{ $item }}</span>
                                @endforeach
                            </div>

                            @include('ads.render', ['campaigns' => $announcementAds ?? collect(), 'placementKey' => 'announcement_strip', 'placementLabel' => 'Announcement'])
                        </div>
                        <div class="col-lg-5">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="p-4 rounded-4 bg-light h-100">
                                        <div class="text-muted small text-uppercase fw-semibold mb-2">Explore the
                                            Journal</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ route('editorial') }}"
                                                class="btn btn-outline-dark btn-sm">Editorial</a>
                                            <a href="{{ route('editorial.board') }}"
                                                class="btn btn-outline-dark btn-sm">Editorial Board</a>
                                            <a href="{{ route('editorial.advisers') }}"
                                                class="btn btn-outline-dark btn-sm">Advisers</a>
                                            <a href="{{ route('scope.policies') }}"
                                                class="btn btn-outline-dark btn-sm">Scope & Policies</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-4 rounded-4 text-white"
                                        style="background:linear-gradient(135deg,#0f172a,#1d4ed8);">
                                        <div class="text-uppercase small fw-semibold opacity-75 mb-2">Current Notice
                                        </div>
                                        <h5 class="fw-bold mb-2">Call for Papers</h5>
                                        <p class="mb-3 text-white-50">Review submission expectations, deadlines, fees,
                                            and contact details in one place.</p>
                                        <a href="{{ route('call.for.papers') }}" class="btn btn-warning btn-sm">View
                                            Call for Papers</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @include('ads.render', ['campaigns' => $homepageAds ?? collect(), 'placementKey' => 'homepage_banner', 'placementLabel' => 'Advertisement'])

    @if(isset($featuredIssue) && $featuredIssue)
    <section class="py-5">
        <div class="container">
            <div class="card border-0 shadow-lg overflow-hidden" data-aos="fade-up" style="border-radius: 1.5rem;">
                <div class="row g-0 align-items-center">
                    <div class="col-lg-7">
                        <div class="p-4 p-lg-5">
                            <h2 class="fw-bold mb-3">{{ $featuredIssue->title }}</h2>

                            <p class="text-muted mb-3">
                                @if(!empty($featuredIssue->volume))
                                {{ $featuredIssue->volume->title }}
                                @endif
                                @if(!empty($featuredIssue->issue_no))
                                • Issue {{ $featuredIssue->issue_no }}
                                @endif
                            </p>

                            <p class="mb-4 text-muted">
                                This featured issue contains {{ $featuredIssue->articles->count() }} published
                                article(s) and highlights recent contributions in the journal.
                            </p>

                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('issue.show', $featuredIssue->id) }}" class="btn btn-primary">
                                    Open Featured Issue
                                </a>
                                <a href="{{ route('issue.pdf', $featuredIssue->id) }}" class="btn btn-outline-dark">
                                    Download Issue PDF
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="h-100 d-flex align-items-center justify-content-center text-white"
                            style="min-height: 280px; background: linear-gradient(135deg, #4f46e5, #0f172a);">
                            <div class="text-center p-4">
                                <i class="bi bi-journal-bookmark" style="font-size: 4rem; opacity: .9;"></i>
                                <div class="mt-3 fs-5 fw-semibold">OJMAS Featured Issue</div>
                                <div class="small opacity-75">
                                    {{ $featuredIssue->published_on ? \Carbon\Carbon::parse($featuredIssue->published_on)->format('d M Y') : $featuredIssue->created_at?->format('d M Y') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif

    <section class="stats-section py-5">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-6 col-lg-3" data-aos="fade-up">
                    <div class="card stat-card h-100 p-3">
                        <div class="stat-number">{{ $published->count() ?? 0 }}</div>
                        <div class="text-muted">Published Articles</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="card stat-card h-100 p-3">
                        <div class="stat-number">24/7</div>
                        <div class="text-muted">Submission Access</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="card stat-card h-100 p-3">
                        <div class="stat-number">4+</div>
                        <div class="text-muted">User Roles</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="card stat-card h-100 p-3">
                        <div class="stat-number">100%</div>
                        <div class="text-muted">Digital Workflow</div>
                    </div>
                </div>
            </div>
        </div>
        <section class="py-5">
            <div class="container py-lg-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end mb-4">
                    <div>
                        <h2 class="section-title mb-2" data-aos="fade-up">Recent Issues</h2>
                        <p class="section-subtitle mb-0" data-aos="fade-up" data-aos-delay="100">
                            Browse the latest journal issues and explore grouped publications by volume and issue.
                        </p>
                    </div>
                    <div class="mt-3 mt-lg-0" data-aos="fade-left">
                        <a href="{{ route('issues.index') }}" class="btn btn-outline-primary">
                            View All Issues
                        </a>
                    </div>
                </div>

                @if(isset($latestIssues) && $latestIssues->count())
                <div class="row g-4">
                    @foreach($latestIssues as $issue)
                    <div class="col-md-6 col-lg-3" data-aos="fade-up">
                        <div class="card article-card h-100">
                            <div class="card-body p-4">
                                @if(!empty($issue->volume))
                                <span class="badge bg-primary-subtle text-primary mb-3">
                                    {{ $issue->volume->title }}
                                </span>
                                @endif

                                <h5 class="card-title mb-3">{{ $issue->title }}</h5>

                                <p class="card-text text-muted mb-3">
                                    {{ $issue->articles->count() }} article(s)
                                </p>

                                <small class="d-block text-muted mb-3">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    {{ $issue->published_on?->format('d M Y') ?? $issue->created_at?->format('d M Y') }}
                                </small>

                                <a href="{{ route('issue.show', $issue->id) }}" class="btn btn-sm btn-outline-dark">
                                    Open Issue
                                </a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </section>
    </section>

    <section id="features" class="py-5">
        <div class="container py-lg-4">
            <div class="text-center mb-5">
                <h2 class="section-title" data-aos="fade-up">Platform Features</h2>
                <p class="section-subtitle mx-auto" data-aos="fade-up" data-aos-delay="100">
                    OJMAS is built to support the full lifecycle of scholarly publishing with speed,
                    transparency, and reliability.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4" data-aos="fade-up">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-cloud-arrow-up"></i></div>
                        <h5 class="fw-bold">Article Submission</h5>
                        <p class="text-muted mb-0">Authors can submit manuscripts, manage co-authors, upload files, and
                            track the status of every submission.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-people"></i></div>
                        <h5 class="fw-bold">Role-Based Workflow</h5>
                        <p class="text-muted mb-0">Dedicated dashboards for admins, editors, reviewers, and authors to
                            ensure an organized publication process.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-search"></i></div>
                        <h5 class="fw-bold">Peer Review System</h5>
                        <p class="text-muted mb-0">Editors can assign reviewers, monitor review decisions, and keep
                            editorial decisions documented.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4" data-aos="fade-up">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-credit-card"></i></div>
                        <h5 class="fw-bold">Payments & Receipts</h5>
                        <p class="text-muted mb-0">Integrated payment tracking, invoice handling, and receipt generation
                            for journal vetting and processing fees.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-journal-bookmark"></i></div>
                        <h5 class="fw-bold">Volumes & Issues</h5>
                        <p class="text-muted mb-0">Organize accepted articles into volumes and issues for proper
                            indexing and structured publication.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-globe2"></i></div>
                        <h5 class="fw-bold">Public Archives</h5>
                        <p class="text-muted mb-0">Readers can explore published issues and articles through a clean
                            archive and article reading interface.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-light">
        <div class="container py-lg-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end mb-4">
                <div>
                    <h2 class="section-title mb-2" data-aos="fade-up">Latest Published Articles</h2>
                    <p class="section-subtitle mb-0" data-aos="fade-up" data-aos-delay="100">
                        Read recent scholarly contributions published through the OJMAS platform.
                    </p>
                </div>
                <div class="mt-3 mt-lg-0" data-aos="fade-left">
                    <a href="{{ route('archives') }}" class="btn btn-outline-primary">
                        View All Archives
                    </a>
                </div>
            </div>

            @if(isset($published) && $published->count())
            <div class="row g-4">
                @foreach($published as $item)
                <div class="col-md-6 col-lg-4" data-aos="fade-up">
                    <div class="card article-card h-100">
                        <div class="card-body p-4">
                            <span class="badge bg-primary-subtle text-primary mb-3">Published</span>
                            <h5 class="card-title mb-3">{{ $item->title }}</h5>
                            <p class="card-text text-muted">
                                {{ \Illuminate\Support\Str::limit($item->abstract, 140) }}
                            </p>
                        </div>
                        <div class="card-footer bg-white border-0 px-4 pb-4">
                            <small class="d-block text-muted mb-2">
                                <i class="bi bi-person-circle me-1"></i>
                                {{ $item->author->name ?? 'Unknown Author' }}
                            </small>

                            @if(!empty($item->issue))
                            <small class="d-block text-muted mb-3">
                                <i class="bi bi-journal-bookmark me-1"></i>
                                {{ $item->issue->title }}
                            </small>
                            @endif

                            <a href="{{ route('article.show', $item->slug) }}" class="btn btn-sm btn-outline-dark">
                                Read Article
                            </a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="alert alert-info shadow-sm">
                No published articles yet.
            </div>
            @endif
        </div>
    </section>

    <section id="about" class="about-section py-5">
        <div class="container py-lg-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6" data-aos="fade-right">
                    <img src="{{ asset('images/backgrounds/about-editorial.jpg') }}" alt="About OJMAS"
                        class="img-fluid rounded-4 shadow-lg">
                </div>

                <div class="col-lg-6" data-aos="fade-left">
                    <h2 class="section-title mb-3">About OJMAS</h2>
                    <p class="text-muted">
                        OJMAS is designed as a complete e-publishing and academic journal management solution.
                        It supports manuscript submission, editorial review, peer review coordination, publication
                        workflows, payments, and digital archiving in one unified platform.
                    </p>
                    <p class="text-muted">
                        The journal is proudly published by {{ $journalHighlights['publisher'] }} and coordinated
                        through its editorial office in Offa, Kwara State, creating a credible base for
                        interdisciplinary academic engagement.
                    </p>

                    <div class="row g-3 mt-2">
                        <div class="col-sm-6">
                            <div class="card about-card p-3 h-100">
                                <h6 class="fw-bold mb-2"><i class="bi bi-shield-check text-primary me-2"></i>Secure
                                    Access</h6>
                                <p class="text-muted mb-0 small">Protected data, authenticated dashboards, and organized
                                    access control.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="card about-card p-3 h-100">
                                <h6 class="fw-bold mb-2"><i class="bi bi-diagram-3 text-primary me-2"></i>Structured
                                    Workflow</h6>
                                <p class="text-muted mb-0 small">Clear process from author submission to final
                                    publication.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section py-5">
        <div class="container text-center py-lg-4">
            <h2 class="fw-bold mb-3" data-aos="zoom-in">Ready to Publish Your Research?</h2>
            <p class="mx-auto mb-4" style="max-width: 760px;" data-aos="zoom-in" data-aos-delay="100">
                Join authors, editors, and reviewers in a streamlined scholarly publishing environment tailored for
                modern academic excellence.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3" data-aos="zoom-in" data-aos-delay="200">
                <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-4">Get Started</a>
                <a href="{{ route('call.for.papers') }}" class="btn btn-warning btn-lg px-4">Call for Papers</a>
                <a href="#contact" class="btn btn-outline-light btn-lg px-4">Contact Us</a>
            </div>
        </div>
    </section>

    <section id="contact" class="py-5">
        <div class="container py-lg-4">
            <div class="text-center mb-5">
                <h2 class="section-title" data-aos="fade-up">Contact Us</h2>
                <p class="section-subtitle mx-auto" data-aos="fade-up" data-aos-delay="100">
                    Reach out for submission guidance, editorial support, technical help, or partnership enquiries.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-lg-5" data-aos="fade-right">
                    <div class="card contact-card p-4 h-100">
                        <h4 class="fw-bold mb-4">Contact Information</h4>

                        <p class="contact-info"><i class="bi bi-envelope-fill"></i> {{ $journalHighlights['email'] }}
                        </p>
                        <p class="contact-info"><i class="bi bi-telephone-fill"></i> +234 913 494 3054
                        </p>
                        <p class="contact-info"><i class="bi bi-geo-alt-fill"></i> {{ $journalHighlights['office'] }}
                        </p>
                        <p class="contact-info"><i class="bi bi-clock-fill"></i> Office | Mon - Fri, 9:00 AM - 5:00 PM
                        </p>
                        <p class="contact-info"><i class="bi bi-clock-fill"></i> Online | 24/7 Support</p>
                    </div>
                </div>

                <div class="col-lg-7" data-aos="fade-left">
                    <div class="card contact-card p-4">
                        <h4 class="fw-bold mb-4">Send a Message</h4>

                        <form method="POST" action="{{ route('contact.store') }}">
                            @csrf

                            @if(session('success'))
                            <div class="alert alert-success rounded-4 shadow-sm mb-4">
                                {{ session('success') }}
                            </div>
                            @endif

                            @if($errors->any())
                            <div class="alert alert-danger rounded-4 shadow-sm mb-4">
                                <ul class="mb-0 ps-3">
                                    @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="text" name="full_name" class="form-control form-control-lg"
                                        placeholder="Full Name" value="{{ old('full_name') }}" required>
                                </div>

                                <div class="col-md-6">
                                    <input type="email" name="email" class="form-control form-control-lg"
                                        placeholder="Email Address" value="{{ old('email') }}" required>
                                </div>

                                <div class="col-12">
                                    <input type="text" name="subject" class="form-control form-control-lg"
                                        placeholder="Subject" value="{{ old('subject') }}" required>
                                </div>

                                <div class="col-12">
                                    <textarea name="message" class="form-control" rows="6"
                                        placeholder="Write your message here..."
                                        required>{{ old('message') }}</textarea>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg px-4">Send Message</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer py-4">
        <div class="container">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6">
                    <p class="mb-2">&copy; {{ date('Y') }} OJMAS. All rights reserved.</p>
                    <p class="mb-1 small">P-ISSN: 3121-7508 | E-ISSN: 3156-5182</p>
                    <p class="mb-3 small">License: {{ $journalHighlights['license_version'] }}</p>
                    <div class="d-flex align-items-center gap-3">
                        <a href="#" class="text-decoration-none text-light" aria-label="Facebook">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" class="text-decoration-none text-light" aria-label="X">
                            <i class="bi bi-twitter-x"></i>
                        </a>
                        <a href="#" class="text-decoration-none text-light" aria-label="Instagram">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="#" class="text-decoration-none text-light" aria-label="LinkedIn">
                            <i class="bi bi-linkedin"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h6 class="text-white fw-bold mb-3 text-lg-end text-start">Quick Links</h6>
                    <div class="row row-cols-2 g-1 text-lg-end text-start small">
                        <div class="col"><a href="{{ route('home') }}">Home</a></div>
                        <div class="col"><a href="{{ route('editorial') }}">Editorial</a></div>
                        <div class="col"><a href="{{ route('editorial.board') }}">Editorial Board</a></div>
                        <div class="col"><a href="{{ route('editorial.advisers') }}">Advisers</a></div>
                        <div class="col"><a href="{{ route('scope.policies') }}">Scope & Policies</a></div>
                        <div class="col"><a href="{{ route('call.for.papers') }}">Call for Papers</a></div>
                        <div class="col"><a href="{{ route('archives') }}">Archives</a></div>
                        <div class="col"><a href="{{ route('issues.index') }}">Issues</a></div>
                        <div class="col"><a href="#about">About</a></div>
                        <div class="col"><a href="#contact">Contact</a></div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
    AOS.init({
        duration: 900,
        once: true,
        easing: 'ease-in-out'
    });
    </script>
</body>

</html>
