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
            linear-gradient(135deg, rgba(15,23,42,.92), rgba(16,185,129,.82)),
            url('{{ asset('images/backgrounds/auth-side.jpg') }}') center center / cover no-repeat;
        color: #fff;
        position: relative;
    }

    .auth-side::before{
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at bottom left, rgba(13,110,253,.18), transparent 28%);
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
                                            <i class="bi bi-key"></i>
                                        </div>
                                        <h2 class="fw-bold mb-3">Create a New Password</h2>
                                        <p class="mb-0 text-white-50">
                                            Set a strong password to secure your journal account and continue your work safely.
                                        </p>
                                    </div>

                                    <div>
                                        <div class="d-flex mb-3">
                                            <i class="bi bi-check-circle-fill me-2 text-light"></i>
                                            <span>Use a secure password you can remember.</span>
                                        </div>
                                        <div class="d-flex">
                                            <i class="bi bi-check-circle-fill me-2 text-light"></i>
                                            <span>Your account protection helps secure submissions and review records.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="auth-form-area p-4 p-md-5">
                                <div class="mb-4" data-aos="fade-up">
                                    <h3 class="auth-title mb-2">Reset Password</h3>
                                    <p class="auth-subtitle mb-0">
                                        Enter your email address and choose a new password.
                                    </p>
                                </div>

                                @if($errors->any())
                                    <div class="alert alert-danger rounded-4" data-aos="fade-up">
                                        <ul class="mb-0 ps-3">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('password.update') }}" data-aos="fade-up" data-aos-delay="100">
                                    @csrf

                                    <input type="hidden" name="token" value="{{ request()->route('token') }}">

                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-semibold">Email Address</label>
                                        <input
                                            id="email"
                                            type="email"
                                            name="email"
                                            value="{{ old('email', request('email')) }}"
                                            class="form-control"
                                            placeholder="Enter your email"
                                            required
                                            autofocus
                                        >
                                    </div>

                                    <div class="mb-3">
                                        <label for="password" class="form-label fw-semibold">New Password</label>
                                        <input
                                            id="password"
                                            type="password"
                                            name="password"
                                            class="form-control"
                                            placeholder="Create a new password"
                                            required
                                        >
                                    </div>

                                    <div class="mb-4">
                                        <label for="password_confirmation" class="form-label fw-semibold">Confirm Password</label>
                                        <input
                                            id="password_confirmation"
                                            type="password"
                                            name="password_confirmation"
                                            class="form-control"
                                            placeholder="Confirm your new password"
                                            required
                                        >
                                    </div>

                                    <button type="submit" class="btn btn-success auth-btn w-100">
                                        <i class="bi bi-shield-check me-2"></i>Reset Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-center mt-4 footer-mini mb-0">
                    OJMAS &mdash; Account security and recovery
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
