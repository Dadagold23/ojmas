<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    <title>{{ config('app.name', 'OJMAS') }}</title>

    <meta name="description" content="OJMAS Management System">
    <link rel="icon" href="{{ asset('images/favicon.png') }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <style>
        :root{
            --oj-primary:#0d6efd;
            --oj-dark:#0f172a;
            --oj-slate:#1e293b;
            --oj-light:#f8fafc;
            --oj-muted:#64748b;
            --oj-accent:#f59e0b;
        }

        body{
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--oj-light);
            color: #1e293b;
        }

        .oj-navbar{
            background: linear-gradient(90deg, rgba(15,23,42,.96), rgba(30,41,59,.96));
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 30px rgba(15, 23, 42, .08);
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .oj-brand{
            font-weight: 800;
            letter-spacing: .4px;
            max-width: calc(100% - 72px);
            line-height: 1.1;
            white-space: normal;
        }

        .oj-brand i{
            color: var(--oj-accent);
        }

        .oj-main{
            min-height: calc(100vh - 72px);
        }

        .oj-card{
            border: none;
            border-radius: 1.25rem;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .08);
        }

        .oj-section-title{
            color: var(--oj-dark);
            font-weight: 800;
        }

        .oj-btn-primary{
            background: linear-gradient(135deg, var(--oj-primary), #2563eb);
            border: none;
        }

        .oj-btn-primary:hover{
            background: linear-gradient(135deg, #0b5ed7, #1d4ed8);
        }

        .dropdown-menu{
            border: none;
            border-radius: 1rem;
            box-shadow: 0 18px 40px rgba(15,23,42,.12);
            z-index: 1031;
        }

        .footer-mini{
            color: var(--oj-muted);
            font-size: .92rem;
        }

        .footer{
            background:#0f172a;
            color:rgba(255,255,255,.82);
        }

        .footer a{
            color:rgba(255,255,255,.88);
        }

        .footer a:hover{
            color:#fff;
        }

        .pagination{
            --bs-pagination-padding-x: .95rem;
            --bs-pagination-padding-y: .6rem;
            --bs-pagination-font-size: .95rem;
            --bs-pagination-color: var(--oj-slate);
            --bs-pagination-bg: #fff;
            --bs-pagination-border-color: #dbe4f0;
            --bs-pagination-hover-color: var(--oj-primary);
            --bs-pagination-hover-bg: #eff6ff;
            --bs-pagination-hover-border-color: #bfdbfe;
            --bs-pagination-focus-color: var(--oj-primary);
            --bs-pagination-focus-bg: #dbeafe;
            --bs-pagination-focus-box-shadow: 0 0 0 .18rem rgba(13, 110, 253, .14);
            --bs-pagination-active-color: #fff;
            --bs-pagination-active-bg: var(--oj-primary);
            --bs-pagination-active-border-color: var(--oj-primary);
            --bs-pagination-disabled-color: #94a3b8;
            --bs-pagination-disabled-bg: #f8fafc;
            --bs-pagination-disabled-border-color: #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: .45rem;
            margin-bottom: 0;
        }

        .pagination .page-item{
            display: flex;
        }

        .pagination .page-link{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.75rem;
            min-height: 2.75rem;
            padding: .6rem .95rem;
            border-radius: .95rem !important;
            border-width: 1px;
            font-weight: 600;
            line-height: 1;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
            transition: background-color .2s ease, border-color .2s ease, color .2s ease, transform .2s ease;
        }

        .pagination .page-link:hover{
            transform: translateY(-1px);
        }

        .pagination .page-item.active .page-link{
            box-shadow: 0 12px 28px rgba(37, 99, 235, .22);
        }

        .pagination .page-item.disabled .page-link{
            box-shadow: none;
        }

        .pagination .page-link svg{
            width: 1rem;
            height: 1rem;
            flex: 0 0 1rem;
        }

        .pagination-wrap{
            display: flex;
            justify-content: center;
            width: 100%;
        }

        /* Prevent AOS-animated cards from stacking above the navbar */
        [data-aos] {
            z-index: auto !important;
        }

        .oj-main {
            position: relative;
            z-index: 1;
        }

        @media (max-width: 991.98px){
            .navbar-collapse{
                margin-top: .85rem;
                padding: 1rem;
                border-radius: 1rem;
                background: rgba(15,23,42,.96);
            }
        }

        @media (max-width: 575.98px){
            .oj-brand{
                font-size: .95rem;
                max-width: calc(100% - 60px);
            }

            .pagination{
                gap: .35rem;
            }

            .pagination .page-link{
                min-width: 2.45rem;
                min-height: 2.45rem;
                padding: .5rem .8rem;
                font-size: .9rem;
            }
        }
    </style>
</head>
<body>
    @unless(\View::hasSection('hide_chrome'))
    <nav class="navbar navbar-expand-lg navbar-dark oj-navbar py-3">
        <div class="container">
            <a class="navbar-brand oj-brand" href="{{ route('home') }}">
                <i class="bi bi-journal-richtext me-2"></i>OJMAS
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ojNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="ojNavbar">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('home') }}">Home</a>
                    </li>

                    @if(Route::has('archives'))
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('archives') }}">Archives</a>
                    </li>
                    @endif

                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('issues.index') }}">Issues</a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Journal
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('editorial') }}">Editorial</a></li>
                            <li><a class="dropdown-item" href="{{ route('editorial.board') }}">Editorial Board</a></li>
                            <li><a class="dropdown-item" href="{{ route('editorial.advisers') }}">Editorial Advisers</a></li>
                            <li><a class="dropdown-item" href="{{ route('scope.policies') }}">Scope & Policies</a></li>
                            <li><a class="dropdown-item" href="{{ route('call.for.papers') }}">Call for Papers</a></li>
                        </ul>
                    </li>

                    @auth
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    @if(auth()->user()->hasAnyRole(['Super Admin', 'Admin', 'Managing Editor', 'Editor']))
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Manage
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('editor.volumes.index') }}"><i class="bi bi-journal-bookmark me-2"></i>Volumes</a></li>
                            <li><a class="dropdown-item" href="{{ route('editor.issues.index') }}"><i class="bi bi-journals me-2"></i>Issues</a></li>
                            @if(auth()->user()->hasAnyRole(['Super Admin', 'Admin', 'Managing Editor', 'Editor']))
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('admin.publications.index') }}"><i class="bi bi-journal-check me-2"></i>Publications</a></li>
                            @endif
                            @if(auth()->user()->hasAnyRole(['Super Admin', 'Admin', 'Managing Editor']))
                            <li><a class="dropdown-item" href="{{ route('admin.users.index') }}"><i class="bi bi-people me-2"></i>Users</a></li>
                            @endif
                        </ul>
                    </li>
                    @endif
                    @endauth
                </ul>

                <div class="d-flex align-items-center gap-2">
                    @auth
                        <div class="dropdown">
                            <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="{{ route('dashboard') }}">
                                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button class="dropdown-item text-danger" type="submit">
                                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm px-3">Login</a>
                        @if(Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-warning btn-sm px-3">Register</a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>
    @endunless

    <main class="oj-main py-4">
        @yield('content')
        <div class="container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show rounded-4" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show rounded-4" role="alert">
                    {{ session('info') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show rounded-4" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
        </div>
    </main>

    @unless(\View::hasSection('hide_chrome'))
    @include('layouts.footer')
    @endunless

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 850,
            once: true,
            easing: 'ease-in-out'
        });
    </script>
    @stack('modals')
    @stack('scripts')
</body>
</html>
