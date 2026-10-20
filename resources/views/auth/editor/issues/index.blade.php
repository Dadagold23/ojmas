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
            <h3 class="page-title mb-1">Journal Issues</h3>
            <p class="text-muted mb-0">Manage issue records and publication organization.</p>
        </div>
        @if(Route::has('editor.issues.create'))
            <a href="{{ route('editor.issues.create') }}" class="btn btn-primary">Create Issue</a>
        @endif
    </div>

    <div class="card panel-card" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Issue List</h5>
            <p class="text-muted mb-3">All issues created under available journal volumes.</p>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Volume</th>
                            <th>Issue No</th>
                            <th>Title</th>
                            <th>Published Date</th>
                            <th>Status</th>
                            <th class="pe-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($issues ?? [] as $issue)
                            <tr>
                                <td class="px-4">{{ $issue->volume->title ?? 'N/A' }}</td>
                                <td>{{ $issue->issue_no ?? '-' }}</td>
                                <td>{{ $issue->title }}</td>
                                <td>{{ $issue->published_on?->format('d M Y') ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $issue->status === 'published' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($issue->status ?? 'draft') }}
                                    </span>
                                </td>
                                <td class="pe-4">
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="{{ route('editor.issues.toggle-status', $issue->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                {{ $issue->status === 'published' ? 'Set Draft' : 'Publish' }}
                                            </button>
                                        </form>
                                        <a href="{{ route('editor.issues.show', $issue->id) }}" class="btn btn-sm btn-outline-primary">
                                            Open
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">No issues available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
