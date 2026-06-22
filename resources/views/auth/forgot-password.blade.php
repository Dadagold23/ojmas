@extends('layouts.app')

@section('content')
<style>
    .auth-wrap{
        min-height: calc(100vh - 120px);
        display: flex;
        align-items: center;
        background:
            linear-gradient(rgba(248,250,252,.86), rgba(248,250,252,.92)),
            url('{{ asset('images/backgrounds/auth-bg.jpg') }}') center center / cover no-repeat;
    }

    .auth-card{
        border: none;
        border-radius: 1.5rem;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(15, 23, 42, .12);
    }

    .auth-side{
        min-height: 100%;
        background:
            linear-gradient(135deg, rgba(15,23,42,.92), rgba(37,99,235,.88)),
            url('{{ asset('images/backgrounds/auth-side.jpg') }}') center center / cover no-repeat;
        color: #fff;
        position: relative;
    }

    .auth-side::before{
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(245,158,11,.22), transparent 25%);
    }

    .auth-side-content{
        position: relative;
        z-index: 2;
    }

    .auth-logo-box{
        width: 78px;
        height: 78px;
        border-radius: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.18);
        font-size: 2rem;
    }

    .auth-form-area{
        background: #fff;
    }

    .auth-title{
        font-weight: 800;
        color: #0f172a;
    }

    .auth-subtitle{
        color: #64748b;
    }

    .form-control{
        border-radius: .9rem;
        padding: .9rem 1rem;
    }

    .form-control:focus{
        box-shadow: 0 0 0 .25rem rgba(13,110,253,.12);
        border-color: #86b7fe;
    }

    .auth-btn{
        border-radius: .9rem;
        padding: .9rem 1rem;
        font-weight: 600;
    }
</style>

<div class="auth-wrap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">
                <div class="card auth-card">
                    <div class="row g-0">
                        <div class="col-lg-5 d-none d-lg-block">
                            <div class="auth-side h-100 p-5">
                                <div class="auth-side-content d-flex flex-column justify-content-between h-100">
                                    <div>
                                        <div class="auth-logo-box mb-4">
                                            <i class="bi bi-shield-lock"></i>
                                        </div>
                                        <h2 class="fw-bold mb-3">Password Recovery</h2>
                                        <p class="mb-0 text-white-50">
                                            Enter your email address and we’ll send you a secure password reset link.
                                        </p>
                                    </div>

                                    <div>
                                        <div class="d-flex mb-3">
                                            <i class="bi bi-check-circle-fill me-2 text-warning"></i>
                                            <span>Reset access securely and continue your publishing work.</span>
                                        </div>
                                        <div class="d-flex">
                                            <i class="bi bi-check-circle-fill me-2 text-warning"></i>
                                            <span>Fast recovery for authors, reviewers, editors, and admins.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="auth-form-area p-4 p-md-5">
                                <div class="mb-4" data-aos="fade-up">
                                    <h3 class="auth-title mb-2">Forgot your password?</h3>
                                    <p class="auth-subtitle mb-0">
                                        No problem. Enter your email address and we will send you a password reset link.
                                    </p>
                                </div>

                                @if (session('status'))
                                    <div class="alert alert-success rounded-4" data-aos="fade-up">
                                        {{ session('status') }}
                                    </div>
                                @endif

                                @if($errors->any())
                                    <div class="alert alert-danger rounded-4" data-aos="fade-up">
                                        <ul class="mb-0 ps-3">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('password.email') }}" data-aos="fade-up" data-aos-delay="100">
                                    @csrf

                                    <div class="mb-4">
                                        <label for="email" class="form-label fw-semibold">Email Address</label>
                                        <input
                                            id="email"
                                            type="email"
                                            name="email"
                                            value="{{ old('email') }}"
                                            class="form-control"
                                            placeholder="Enter your email"
                                            required
                                            autofocus
                                        >
                                    </div>

                                    <button type="submit" class="btn btn-primary auth-btn w-100 mb-3">
                                        <i class="bi bi-envelope-paper me-2"></i>Email Password Reset Link
                                    </button>

                                    <div class="text-center">
                                        <a href="{{ route('login') }}" class="text-decoration-none fw-semibold">Back to login</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-center mt-4 footer-mini mb-0">
                    OJMAS &mdash; Secure account recovery
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
