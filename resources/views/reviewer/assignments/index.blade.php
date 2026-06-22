@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .reviewer-hero { border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(16,185,129,.92), rgba(15,23,42,.92));
        color: #fff; box-shadow: 0 20px 50px rgba(15,23,42,.16); }
    .status-badge { font-size: .78rem; padding: .35em .75em; border-radius: 50px; }
</style>

<div class="container py-4">

    <div class="card reviewer-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-light text-success mb-3 px-3 py-2">Reviewer Workspace</span>
                    <h2 class="fw-bold mb-2">My Review Assignments</h2>
                    <p class="mb-0 text-white-50">Open any assignment to read the manuscript files and submit your recommendation.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <i class="bi bi-clipboard-check" style="font-size:5rem;opacity:.15"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card panel-card" data-aos="fade-up">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Manuscript Title</th>
                            <th>Submission No</th>
                            <th>Assigned</th>
                            <th>Due Date</th>
                            <th>Files</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assignments as $assignment)
                        <tr>
                            <td class="px-4 fw-semibold small">{{ Str::limit($assignment->submission->title ?? 'N/A', 55) }}</td>
                            <td class="small text-muted">{{ $assignment->submission->submission_no ?? '-' }}</td>
                            <td class="small text-muted">{{ $assignment->created_at?->format('d M Y') }}</td>
                            <td class="small">
                                @if($assignment->due_date)
                                    @php $overdue = $assignment->due_date->isPast() && $assignment->status !== 'completed'; @endphp
                                    <span class="{{ $overdue ? 'text-danger fw-semibold' : 'text-muted' }}">
                                        {{ $assignment->due_date->format('d M Y') }}
                                        @if($overdue) <i class="bi bi-exclamation-circle ms-1"></i> @endif
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $assignment->submission->files->count() }} file(s)</td>
                            <td>
                                <span class="badge bg-{{ $assignment->status === 'completed' ? 'success' : 'warning' }} status-badge">
                                    {{ ucfirst($assignment->status ?? 'pending') }}
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <a href="{{ route('reviewer.assignments.show', $assignment->id) }}"
                                   class="btn btn-sm btn-outline-primary fw-semibold">
                                    <i class="bi bi-eye me-1"></i>Open & Download
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-clipboard fs-2 d-block mb-2 opacity-25"></i>
                                No assignments yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($assignments->hasPages())
        <div class="card-footer bg-white border-0 px-4 pb-4">
            {{ $assignments->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
