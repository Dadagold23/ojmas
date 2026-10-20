@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-danger px-3 py-2 mb-2">System configuration</span>
            <h3 class="fw-bold mb-1">Journal style profiles</h3>
            <p class="text-muted mb-0">
                Formatting rules live here, not in code. A rule change takes effect on the next formatting run.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success rounded-4 mb-4">{{ session('success') }}</div>
    @endif

    @foreach($profiles as $profile)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-0">{{ $profile->name }}
                        <small class="text-muted fw-normal">· {{ $profile->slug }}</small>
                    </h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge {{ $profile->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $profile->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <form method="POST" action="{{ route('admin.configuration.style-profiles.toggle', $profile) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary">{{ $profile->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.configuration.style-profiles.update', $profile) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Profile name</label>
                            <input type="text" name="name" value="{{ $profile->name }}" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Version</label>
                            <input type="text" name="version" value="{{ $profile->version }}" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold d-block">Active</label>
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" id="active-{{ $profile->id }}" class="form-check-input" @checked($profile->is_active)>
                                <label class="form-check-label small" for="active-{{ $profile->id }}">Used for new submissions</label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Title, maximum words</label>
                            <input type="number" name="rules[title_max_words]" value="{{ $profile->rules_json['title_max_words'] ?? '' }}" min="1" max="100" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Abstract, maximum words</label>
                            <input type="number" name="rules[abstract_max_words]" value="{{ $profile->rules_json['abstract_max_words'] ?? '' }}" min="1" max="2000" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Paper size</label>
                            <input type="text" name="rules[paper_label]" value="{{ $profile->rules_json['paper']['label'] ?? '' }}" class="form-control form-control-sm" readonly>
                        </div>
                    </div>

                    <div class="alert alert-light border small mb-3">
                        Stored rules currently applied: {{ count($profile->rules_json) }}.
                        Only the numeric limits and switches above are editable; the deeper session and width
                        settings are shown read-only because a wrong value there breaks the document renderer.
                    </div>

                    <button class="btn btn-primary btn-sm">Save profile</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
