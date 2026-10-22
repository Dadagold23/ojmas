@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .sched-hero { border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(13,110,253,.92), rgba(15,23,42,.92));
        color:#fff; box-shadow: 0 20px 50px rgba(15,23,42,.16); }
</style>

<div class="container py-4">
    <div class="card sched-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <h2 class="fw-bold mb-2"><i class="bi bi-calendar2-week me-2"></i>Edit schedule — Vol {{ $schedule->volume?->volume_no }} · Issue {{ $schedule->issue?->issue_no }}</h2>
            <p class="mb-0 text-white-50">
                Changing dates keeps the announcement surfaces in step while that is still possible.
                An announcement already underway or a notification already sent is never altered, and the screen says so.
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.schedules.update', $schedule) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="volume_id" value="{{ $schedule->volume_id }}">
        @include('auth.admin.schedules.fields', ['schedule' => $schedule, 'volumes' => $volumes])

        <div class="alert alert-warning rounded-4" role="alert">
            <i class="bi bi-info-circle me-1"></i>
            @if($schedule->campaign && !in_array($schedule->campaign->status, ['draft', 'scheduled'], true))
                The linked announcement is a permanent record ({{ $schedule->campaign->status }}); its recorded window is not moved by edits.
            @endif
            @if($schedule->notification && in_array($schedule->notification->status, ['sent', 'sending', 'failed', 'cancelled'], true))
                The linked notification is {{ $schedule->notification->status }} — notifications already sent cannot be recalled, so it stays as recorded.
            @endif
        </div>

        <div class="d-flex gap-2 mb-5">
            <button type="submit" name="submit_action" value="schedule" class="btn btn-primary">Save and schedule announcement</button>
            <a href="{{ route('admin.schedules.show', $schedule) }}" class="btn btn-outline-dark ms-auto">Back to schedule</a>
        </div>
    </form>
</div>
@endsection
