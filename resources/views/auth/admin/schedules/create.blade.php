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
            <h2 class="fw-bold mb-2"><i class="bi bi-calendar2-plus me-2"></i>New issue schedule</h2>
            <p class="mb-0 text-white-50">
                Record the planned volume, issue and dates. Saving a draft keeps the plan only;
                “Schedule announcement” also prepares the announcement window and optional push — nothing is ever published automatically on the issue itself.
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.schedules.store') }}" enctype="multipart/form-data">
        @csrf
        @include('auth.admin.schedules.fields', ['schedule' => null, 'volumes' => $volumes])

        <div class="d-flex gap-2 mb-5">
            <button type="submit" name="submit_action" value="draft" class="btn btn-outline-secondary">Save draft</button>
            <button type="submit" name="submit_action" value="schedule" class="btn btn-primary">Schedule announcement</button>
            <a href="{{ route('admin.schedules.index') }}" class="btn btn-outline-dark ms-auto">Back to schedules</a>
        </div>
    </form>
</div>
@endsection
