@extends('layouts.app')

@section('content')
<style>
.cfp-hero {
    border: none;
    border-radius: 1.8rem;
    overflow: hidden;
    color: #fff;
    background: linear-gradient(135deg, rgba(15, 23, 42, .92), rgba(37, 99, 235, .9)),
    url('{{ asset('images/journal/call-for-paper.png') }}') center center / cover no-repeat;
    box-shadow: 0 25px 60px rgba(15, 23, 42, .2);
}

.cfp-panel {
    border: none;
    border-radius: 1.4rem;
    box-shadow: 0 18px 42px rgba(15, 23, 42, .08);
}

.cfp-image {
    width: 100%;
    border-radius: 1.25rem;
    box-shadow: 0 18px 36px rgba(15, 23, 42, .12);
}

.cfp-chip {
    display: inline-flex;
    align-items: center;
    padding: .55rem .85rem;
    border-radius: 999px;
    background: #eff6ff;
    color: #0b5ed7;
    font-weight: 700;
    font-size: .92rem;
}

.cfp-stack {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
</style>

<div class="container py-4">
    <div class="card cfp-hero mb-5" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="fw-bold mb-3">Offa Journal of Multidisciplinary and Advanced Studies</h1>
                    <p class="mb-2 fs-5">{{ $call['tagline'] }}</p>
                    <p class="mb-0 text-white-50">ISSN: {{ $call['issn'] }}</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <a href="{{ route('register') }}" class="btn btn-warning btn-lg px-4">
                        Submit Interest
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5" data-aos="fade-right">
            <img src="{{ asset('images/journal/call-for-paper.png') }}" alt="OJMAS call for paper flyer"
                class="cfp-image">
        </div>
        <div class="col-lg-7" data-aos="fade-left">
            <div class="card cfp-panel h-100">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="fw-bold mb-3">Why Publish with OJMAS?</h2>
                    <p class="fs-5">{{ $call['intro'] }}</p>
                    <p class="text-muted mb-4">{{ $call['value'] }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($call['scope'] as $item)
                        <span class="cfp-chip">{{ $item }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6" data-aos="fade-up">
            <div class="card cfp-panel h-100">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Author Guidelines</h3>
                    <ul class="ps-3 mb-0">
                        @foreach($call['authorsGuidelines'] as $item)
                        <li class="mb-3">{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
            <div class="card cfp-panel h-100">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Review and Publication</h3>
                    <ul class="ps-3 mb-4">
                        @foreach($call['reviewPublication'] as $item)
                        <li class="mb-3">{{ $item }}</li>
                        @endforeach
                    </ul>
                    <h4 class="fw-bold mb-3">Submission Notes</h4>
                    <ul class="ps-3 mb-0">
                        @foreach($call['submissionGuidelines'] as $item)
                        <li class="mb-3">{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-12 cfp-stack" data-aos="fade-up" data-aos-delay="100">
            <div class="card cfp-panel">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">OJMAS</h3>
                    <div class="border rounded-4 p-4 bg-light">
                        <div class="fs-5 fw-bold mb-2">{{ $call['ojmasCredentials']['publisher'] }}</div>
                        <p class="mb-2"><strong>Website:</strong> {{ $call['ojmasCredentials']['website'] }}</p>
                        <p class="mb-2"><strong>Email:</strong> {{ $call['ojmasCredentials']['email'] }}</p>
                        <p class="mb-3"><strong>Phone:</strong> {{ $call['ojmasCredentials']['phone'] }}</p>
                        <hr class="my-4">
                        <div class="text-muted small text-uppercase fw-semibold mb-2">Licensing Information</div>
                        <div class="fw-bold mb-2">{{ $call['credentials']['publisher'] }}</div>
                        <p class="mb-0"><strong>License:</strong> {{ $call['credentials']['license'] }}</p>
                    </div>
                </div>
            </div>
            <div class="card cfp-panel">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Enquiries and Contact</h3>
                    <div class="row g-3">
                        @foreach($call['contacts'] as $person)
                        <div class="col-md-6">
                            <div class="border rounded-4 p-4 h-100">
                                <h5 class="fw-bold mb-2">{{ $person['name'] }}</h5>
                                @if(!empty($person['role']))
                                <p class="text-muted fw-semibold mb-2">{{ $person['role'] }}</p>
                                @endif
                                <p class="mb-2"><i
                                        class="bi bi-envelope-fill text-primary me-2"></i>{{ $person['email'] }}</p>
                                <p class="mb-0"><i
                                        class="bi bi-telephone-fill text-primary me-2"></i>{{ $person['phone'] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
