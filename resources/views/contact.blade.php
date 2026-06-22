@extends('layouts.app')

@section('content')
<style>
    .contact-hero {
        border: none;
        border-radius: 1.8rem;
        color: #fff;
        background:
            radial-gradient(circle at top left, rgba(255,255,255,.16), transparent 28%),
            linear-gradient(135deg, #111827, #0f766e 55%, #22c55e 120%);
        box-shadow: 0 22px 55px rgba(15,23,42,.16);
    }

    .contact-card {
        border: none;
        border-radius: 1.35rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }

    .contact-detail {
        border-radius: 1.1rem;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, .2);
    }
</style>

<div class="container py-4">
    <div class="card contact-hero mb-5" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge text-bg-light text-success px-3 py-2 mb-3">Contact OJMAS</span>
                    <h1 class="fw-bold mb-3">Get in Touch</h1>
                    <p class="mb-0 text-white-50">Send enquiries, publication questions, or general messages to the OJMAS team and we will respond through the appropriate channel.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-envelope-paper-fill" style="font-size: 4.8rem; opacity: .16;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5" data-aos="fade-up">
            <div class="card contact-card h-100">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Contact Information</h3>

                    <div class="contact-detail p-4 mb-3">
                        <div class="small text-uppercase text-muted fw-semibold mb-2">Email</div>
                        <div class="fs-5 fw-semibold">support@ojmaspublish.online</div>
                    </div>

                    <div class="contact-detail p-4 mb-3">
                        <div class="small text-uppercase text-muted fw-semibold mb-2">Phone</div>
                        <div class="fs-5 fw-semibold">+234 913 494 3054</div>
                    </div>

                    <div class="contact-detail p-4 mb-3">
                        <div class="small text-uppercase text-muted fw-semibold mb-2">Office</div>
                        <p class="mb-0">Offa, Kwara State, Nigeria</p>
                    </div>

                    <div class="contact-detail p-4">
                        <div class="small text-uppercase text-muted fw-semibold mb-2">Publisher</div>
                        <p class="mb-0">OJMAS</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7" data-aos="fade-up" data-aos-delay="100">
            <div class="card contact-card">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Send a Message</h3>

                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form method="POST" action="{{ route('contact.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="full_name" class="form-label fw-semibold">Full Name</label>
                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                value="{{ old('full_name') }}"
                                class="form-control @error('full_name') is-invalid @enderror"
                                required
                            >
                            @error('full_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror"
                                required
                            >
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label fw-semibold">Subject</label>
                            <input
                                type="text"
                                id="subject"
                                name="subject"
                                value="{{ old('subject') }}"
                                class="form-control @error('subject') is-invalid @enderror"
                                required
                            >
                            @error('subject')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="message" class="form-label fw-semibold">Message</label>
                            <textarea
                                id="message"
                                name="message"
                                rows="6"
                                class="form-control @error('message') is-invalid @enderror"
                                required
                            >{{ old('message') }}</textarea>
                            @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-success btn-lg px-4">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
