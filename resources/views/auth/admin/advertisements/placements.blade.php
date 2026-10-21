@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0"><i class="bi bi-sliders me-2 text-primary"></i>Placement configuration</h3>
        <a href="{{ route('admin.advertisements.index') }}" class="btn btn-outline-secondary btn-sm">Back to campaigns</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
            {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-info rounded-4">
        <i class="bi bi-shield-check me-1"></i>
        Deactivating a placement hides its slot on every public page immediately. Nothing is deleted.
    </div>

    @forelse($placements as $placement)
        <div class="card border-0 rounded-4 shadow-sm mb-3">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.advertisements.placements.update') }}" class="row g-3 align-items-end">
                    @csrf
                    <input type="hidden" name="placement_id" value="{{ $placement->id }}">

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Label</label>
                        <input type="text" name="label" class="form-control" value="{{ $placement->label }}" required>
                        <div class="form-text">Placement key: <code>{{ $placement->key }}</code> — {{ $placement->description }}</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Serving</label>
                        <select name="is_active" class="form-select">
                            <option value="1" @selected($placement->is_active)>Active</option>
                            <option value="0" @selected(! $placement->is_active)>Deactivated</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">Save placement</button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="text-muted">No placements recorded.</div>
    @endforelse
</div>
@endsection
