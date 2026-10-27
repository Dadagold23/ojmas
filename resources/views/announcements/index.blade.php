@extends('layouts.app')

@section('page_title', 'Announcements — '.config('journal.title', config('app.name', 'OJMAS')))

@section('meta_description', 'Journal announcements: new volumes, issues and articles, calls for papers and official notices.')

@section('content')
<div class="container py-4">
    <div class="card oj-card mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <h2 class="oj-section-title mb-2"><i class="bi bi-megaphone me-2"></i>Journal announcements</h2>
            <p class="text-muted mb-0">
                The same announcements sent to subscribers by browser notification, kept here for
                visitors whose browsers cannot receive push messages.
            </p>
        </div>
    </div>

    @forelse($announcements as $announcement)
        <div class="card oj-card mb-3" data-aos="fade-up">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge text-bg-primary">{{ $labels[$announcement->category] ?? ucfirst(str_replace('_', ' ', $announcement->category)) }}</span>
                    <span class="small text-muted">
                        <i class="bi bi-calendar3 me-1"></i>
                        {{ ($announcement->sent_at ?? $announcement->created_at)?->format('j M Y') }}
                    </span>
                </div>

                <h5 class="fw-bold mb-2">{{ $announcement->title }}</h5>
                <p class="mb-2">{{ $announcement->message }}</p>

                @if($announcement->action_url && preg_match('#^https?://#i', $announcement->action_url))
                    <a href="{{ $announcement->action_url }}" class="btn btn-sm btn-outline-primary">
                        Read more <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                @endif
            </div>
        </div>
    @empty
        <div class="alert alert-light border rounded-4">
            <i class="bi bi-journal-plus me-2"></i>
            There are no announcements right now. New volumes, issues and journal notices are published here.
        </div>
    @endforelse

    {{ $announcements->links() }}
</div>
@endsection
