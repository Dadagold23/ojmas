@extends('layouts.app')

@section('page_title', 'Calls for Papers — '.config('journal.title', config('app.name', 'OJMAS')))

@section('meta_description', 'Current calls for papers, upcoming issue announcements and sponsored content from the journal.')

@section('content')
<style>
    .cfp-hero { border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(13,110,253,.95), rgba(15,23,42,.92));
        color: #fff; box-shadow: 0 20px 50px rgba(15,23,42,.16); }
    .cfp-item { border: 1px solid #e2e8f0; border-radius: 1rem; background: #fff; }
</style>

<div class="container py-4">
    <div class="card cfp-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <h2 class="fw-bold mb-2"><i class="bi bi-megaphone me-2"></i>Calls for Papers &amp; Announcements</h2>
            <p class="mb-0 text-white-50">Current calls, upcoming issues and official journal announcements.</p>
        </div>
    </div>

    @include('ads.render', ['campaigns' => $calls, 'placementKey' => 'calls_for_papers', 'placementLabel' => 'Calls for Papers'])

    @if($issueAnnouncements->count())
        <div class="mb-4" data-aos="fade-up">
            <h4 class="section-title fw-bold">Upcoming issues</h4>
        </div>
        @include('ads.render', ['campaigns' => $issueAnnouncements, 'placementKey' => 'issue_announcements', 'placementLabel' => 'Announcements'])
    @endif

    @if($sponsored->count())
        <div class="mb-4" data-aos="fade-up">
            <h4 class="section-title fw-bold">Sponsored content</h4>
        </div>
        @include('ads.render', ['campaigns' => $sponsored, 'placementKey' => 'sponsored_section', 'placementLabel' => 'Sponsored content'])
    @endif

    @if(! $calls->count() && ! $issueAnnouncements->count() && ! $sponsored->count())
        <div class="alert alert-light border rounded-4">
            <i class="bi bi-journal-plus me-2"></i>
            There are no open calls for papers right now. Check back soon — new calls are announced here.
        </div>
    @endif

    <div class="text-center mt-4">
        <a href="{{ route('archives') }}" class="btn btn-outline-primary">
            Browse the journal archive <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
</div>
@endsection
