@extends('layouts.app')

@section('content')
<style>
    .page-title{font-weight:800;color:#0f172a}
    .panel-card{
        border:none;
        border-radius:1.25rem;
        box-shadow:0 18px 40px rgba(15,23,42,.08);
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title mb-1">Create Volume</h3>
            <p class="text-muted mb-0">Add a new journal volume.</p>
        </div>
        <a href="{{ route('editor.volumes.index') }}" class="btn btn-secondary">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger rounded-4">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card panel-card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('editor.volumes.store') }}">
                @csrf

                <div class="row g-4">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Volume Title</label>
                        <input
                            type="text"
                            name="title"
                            class="form-control"
                            value="{{ old('title') }}"
                            placeholder="e.g. Volume 1"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Year</label>
                        <input
                            type="text"
                            name="year"
                            class="form-control"
                            value="{{ old('year', date('Y')) }}"
                            placeholder="2026"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="draft" @selected(old('status') === 'draft')>Draft</option>
                            <option value="published" @selected(old('status') === 'published')>Published</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea
                            name="description"
                            rows="5"
                            class="form-control"
                            placeholder="Optional notes about this volume"
                        >{{ old('description') }}</textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Create Volume</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
