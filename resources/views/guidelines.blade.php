@extends('layouts.app')

@section('content')
<style>
    .guidelines-hero {
        border: none;
        border-radius: 1.8rem;
        color: #fff;
        background:
            radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 30%),
            linear-gradient(135deg, #0f172a, #1d4ed8 52%, #14b8a6 115%);
        box-shadow: 0 22px 55px rgba(15,23,42,.16);
    }

    .guidelines-card {
        border: none;
        border-radius: 1.35rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }

    .guidelines-list li {
        margin-bottom: .9rem;
    }

    .guidelines-note {
        border-radius: 1.25rem;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, .2);
    }
</style>

<div class="container py-4">
    <div class="card guidelines-hero mb-5" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge text-bg-light text-primary px-3 py-2 mb-3">Author Guide</span>
                    <h1 class="fw-bold mb-3">Submission Guidelines</h1>
                    <p class="mb-0 text-white-50">Prepare your manuscript with clarity, originality, and the formatting standards expected by OJMAS before submission.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-journal-text" style="font-size: 4.8rem; opacity: .16;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7" data-aos="fade-up">
            <div class="card guidelines-card h-100">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Manuscript Preparation</h3>
                    <ul class="guidelines-list ps-3 mb-0">
                        <li>Provide a strong abstract or introduction that clearly shows the manuscript's contribution to knowledge.</li>
                        <li>Keep the title concise and within 18 words where possible.</li>
                        <li>Place author names and affiliation details on a separate cover page.</li>
                        <li>Present the abstract on a separate sheet without author names and keep it within 150 words.</li>
                        <li>Use the latest APA referencing style consistently throughout the paper.</li>
                        <li>State the methodology clearly, including design, sample, instruments, and methods of data collection.</li>
                        <li>Where tables are used for results, include concise interpretation and discussion.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
            <div class="card guidelines-card h-100">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="fw-bold mb-4">Submission Notes</h3>
                    <ul class="guidelines-list ps-3 mb-4">
                        <li>Typed manuscripts should be prepared in double spacing on A4 paper.</li>
                        <li>Assessment and publication fees are determined by the Editorial Board.</li>
                        <li>Submit work that is original and not under consideration by another journal.</li>
                    </ul>

                    <div class="guidelines-note p-4">
                        <h5 class="fw-bold mb-3">Submission Address</h5>
                        <p class="mb-0">Editor-In-Chief, OJMAS, Department of English and Literary Studies, Prince Abubakar Audu University, Ayingba, Kogi State.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12" data-aos="fade-up">
            <div class="card guidelines-card">
                <div class="card-body p-4 p-lg-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-8">
                            <h3 class="fw-bold mb-3">Before You Submit</h3>
                            <p class="text-muted mb-0">Confirm that your manuscript is properly formatted, references are complete, and all author information has been separated from the blinded review copy where required.</p>
                        </div>
                        <div class="col-lg-4 text-lg-end">
                            <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-4">Start Submission</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
