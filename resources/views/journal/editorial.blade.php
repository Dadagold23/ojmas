@extends('layouts.app')

@section('content')
<style>
    .editorial-hero {
        border: none;
        border-radius: 1.8rem;
        color: #fff;
        overflow: hidden;
        background:
            radial-gradient(circle at top left, rgba(255,255,255,.15), transparent 28%),
            linear-gradient(135deg, #0f172a, #1d4ed8 58%, #f59e0b 120%);
        box-shadow: 0 24px 60px rgba(15,23,42,.18);
    }

    .editorial-card, .signature-card {
        border: none;
        border-radius: 1.4rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }
</style>

<div class="container py-4">
    <div class="card editorial-hero mb-5" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="fw-bold mb-3">{{ $editorial['title'] }}</h1>
                    <p class="mb-0 text-white-50">A note of appreciation, recognition, and scholarly purpose from the journal leadership.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-megaphone-fill" style="font-size: 4.8rem; opacity: .16;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8" data-aos="fade-right">
            <div class="card editorial-card h-100">
                <div class="card-body p-4 p-lg-5">
                    @foreach($editorial['message'] as $paragraph)
                        <p class="fs-5 lh-lg {{ !$loop->last ? 'mb-4' : 'mb-0' }}">{{ $paragraph }}</p>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-4" data-aos="fade-left">
            <div class="card signature-card h-100">
                <div class="card-body p-4 p-lg-5">
                    <div class="rounded-4 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary mb-4" style="width:72px;height:72px;">
                        <i class="bi bi-feather" style="font-size:1.75rem;"></i>
                    </div>
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Signed By</div>
                    <h3 class="fw-bold mb-2">{{ $editorial['signatory']['name'] }}</h3>
                    <p class="text-primary fw-semibold mb-3">{{ $editorial['signatory']['role'] }}</p>
                    <p class="mb-2">{{ $editorial['signatory']['department'] }}</p>
                    <p class="text-muted mb-0">{{ $editorial['signatory']['institution'] }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
