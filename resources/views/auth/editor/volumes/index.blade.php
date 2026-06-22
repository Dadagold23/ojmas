@extends('layouts.app')

@section('content')
<style>
    .page-title{
        font-weight: 800;
        color: #0f172a;
    }

    .panel-card{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div>
            <h3 class="page-title mb-1">Journal Volumes</h3>
            <p class="text-muted mb-0">Create and organize journal publishing volumes.</p>
        </div>
        @if(Route::has('editor.volumes.create'))
            <a href="{{ route('editor.volumes.create') }}" class="btn btn-primary">Create Volume</a>
        @endif
    </div>

    <div class="card panel-card" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Volume List</h5>
            <p class="text-muted mb-3">All configured journal volumes.</p>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Title</th>
                            <th>Year</th>
                            <th class="pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($volumes ?? [] as $volume)
                            <tr>
                                <td class="px-4 fw-semibold">{{ $volume->title }}</td>
                                <td>{{ $volume->year ?? '-' }}</td>
                                <td class="pe-4">
                                    <span class="badge bg-info">{{ ucfirst($volume->status ?? 'draft') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-5">No volumes found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
