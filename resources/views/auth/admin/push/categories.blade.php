@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0"><i class="bi bi-sliders me-2 text-primary"></i>Push category eligibility</h3>
        <a href="{{ route('admin.push.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
            {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-info rounded-4">
        <i class="bi bi-shield-check me-1"></i>
        Administrators choose which notification categories may be dispatched to subscribers (§3.2).
        Ineligible categories can be composed and previewed but will never be sent.
    </div>

    <form method="POST" action="{{ route('admin.push.categories.update') }}">
        @csrf

        @foreach($settings as $setting)
            <div class="card border-0 rounded-4 shadow-sm mb-2">
                <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold small">{{ $setting->label }}</div>
                        <div class="text-muted" style="font-size:.75rem;">{{ $setting->category }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <input type="hidden" name="settings[{{ $loop->index }}][id]" value="{{ $setting->id }}">
                        <select name="settings[{{ $loop->index }}][is_eligible]" class="form-select form-select-sm" style="width: 170px;">
                            <option value="1" @selected($setting->is_eligible)>Eligible for push</option>
                            <option value="0" @selected(! $setting->is_eligible)>Not eligible</option>
                        </select>
                    </div>
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-primary mt-3">Save eligibility</button>
    </form>
</div>
@endsection
