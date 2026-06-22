@extends('layouts.app')

@section('content')
<style>
    .auth-wrap { min-height: calc(100vh - 120px); display:flex; align-items:center;
        background: linear-gradient(rgba(248,250,252,.86), rgba(248,250,252,.92)),
        url('{{ asset('images/backgrounds/auth-bg.jpg') }}') center center / cover no-repeat; }
    .auth-card { border:none; border-radius:1.5rem; overflow:hidden; box-shadow:0 20px 60px rgba(15,23,42,.12); }
    .auth-side { min-height:100%;
        background: linear-gradient(135deg, rgba(15,23,42,.92), rgba(37,99,235,.88)),
        url('{{ asset('images/backgrounds/auth-side.jpg') }}') center center / cover no-repeat;
        color:#fff; position:relative; }
    .auth-side::before { content:""; position:absolute; inset:0;
        background: radial-gradient(circle at top right, rgba(245,158,11,.22), transparent 25%); }
    .auth-side-content { position:relative; z-index:2; }
    .auth-logo-box { width:78px; height:78px; border-radius:1.25rem; display:flex; align-items:center;
        justify-content:center; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); font-size:2rem; }
    .auth-form-area { background:#fff; }
    .auth-title { font-weight:800; color:#0f172a; }
    .auth-subtitle { color:#64748b; }
    .form-control { border-radius:.9rem; padding:.9rem 1rem; }
    .form-control:focus { box-shadow:0 0 0 .25rem rgba(13,110,253,.12); border-color:#86b7fe; }
    .input-group .form-control { border-radius:.9rem 0 0 .9rem; }
    .input-group .btn { border-radius:0 .9rem .9rem 0; border-color:#dee2e6; }
    .auth-btn { border-radius:.9rem; padding:.9rem 1rem; font-weight:600; }
</style>

<div class="auth-wrap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">
                <div class="card auth-card">
                    <div class="row g-0">

                        {{-- Side panel --}}
                        <div class="col-lg-5 d-none d-lg-block">
                            <div class="auth-side h-100 p-5">
                                <div class="auth-side-content d-flex flex-column justify-content-between h-100">
                                    <div>
                                        <div class="auth-logo-box mb-4">
                                            <i class="bi bi-journal-richtext"></i>
                                        </div>
                                        <h2 class="fw-bold mb-3">Welcome Back</h2>
                                        <p class="mb-0 text-white-50">
                                            Access your OJMAS workspace to manage submissions, reviews, publications, and journal operations.
                                        </p>
                                    </div>
                                    <div>
                                        <div class="d-flex mb-3">
                                            <i class="bi bi-check-circle-fill me-2 text-warning"></i>
                                            <span>Secure login for authors, editors, reviewers, and admins.</span>
                                        </div>
                                        <div class="d-flex mb-3">
                                            <i class="bi bi-check-circle-fill me-2 text-warning"></i>
                                            <span>Track journal workflow and publishing progress in one place.</span>
                                        </div>
                                        <div class="d-flex">
                                            <i class="bi bi-check-circle-fill me-2 text-warning"></i>
                                            <span>Modern scholarly publishing with role-based access.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Form --}}
                        <div class="col-lg-7">
                            <div class="auth-form-area p-4 p-md-5">
                                <div class="mb-4" data-aos="fade-up">
                                    <h3 class="auth-title mb-2">Login to your account</h3>
                                    <p class="auth-subtitle mb-0">Enter your email and password to continue.</p>
                                </div>

                                @if(session('status'))
                                <div class="alert alert-success rounded-4 mb-4">{{ session('status') }}</div>
                                @endif

                                @if($errors->any())
                                <div class="alert alert-danger rounded-4 mb-4">
                                    <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                                </div>
                                @endif

                                <form method="POST" action="{{ route('login') }}" data-aos="fade-up" data-aos-delay="100">
                                    @csrf

                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-semibold">Email Address</label>
                                        <input type="email" id="email" name="email"
                                               class="form-control @error('email') is-invalid @enderror"
                                               value="{{ old('email') }}" placeholder="Enter your email"
                                               required autofocus autocomplete="username">
                                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="password" class="form-label fw-semibold">Password</label>
                                        <div class="input-group">
                                            <input type="password" id="password" name="password"
                                                   class="form-control @error('password') is-invalid @enderror"
                                                   placeholder="Enter your password"
                                                   required autocomplete="current-password">
                                            <button type="button" class="btn btn-outline-secondary"
                                                    onclick="togglePassword('password', this)" tabindex="-1">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remember" id="remember_me">
                                            <label class="form-check-label small" for="remember_me">Remember me</label>
                                        </div>
                                        @if(Route::has('password.request'))
                                        <a class="text-decoration-none small" href="{{ route('password.request') }}">
                                            Forgot password?
                                        </a>
                                        @endif
                                    </div>

                                    <button type="submit" class="btn btn-primary auth-btn w-100 mb-3">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>Log In
                                    </button>

                                    <div class="text-center">
                                        <span class="text-muted">Don't have an account?</span>
                                        <a href="{{ route('register') }}" class="text-decoration-none fw-semibold ms-1">Create one</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
                <p class="text-center mt-4 footer-mini mb-0">OJMAS &mdash; Modern Academic Publishing Platform</p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>
@endsection
