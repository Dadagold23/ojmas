@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .file-row { transition: background .15s; }
    .file-row:hover { background: #f1f5f9; }
    .file-icon { width: 40px; height: 40px; border-radius: .75rem; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
</style>

<div class="container py-4" style="max-width:860px">

    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div>
            <h3 class="fw-bold mb-1">Review Assignment</h3>
            <p class="text-muted small mb-0">Read the manuscript files and submit your recommendation.</p>
        </div>
        <a href="{{ route('reviewer.assignments.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Submission info --}}
    <div class="card panel-card mb-4" data-aos="fade-up">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">{{ $assignment->submission->title ?? 'Untitled Submission' }}</h5>
            <div class="row g-2 text-muted small">
                <div class="col-md-4"><strong>Submission No:</strong> {{ $assignment->submission->submission_no ?? '-' }}</div>
                <div class="col-md-4"><strong>Author:</strong> {{ $assignment->submission->author->name ?? '-' }}</div>
                <div class="col-md-4"><strong>Due Date:</strong> {{ $assignment->due_date?->format('d M Y') ?? '-' }}</div>
                <div class="col-md-4">
                    <strong>Status:</strong>
                    <span class="badge bg-{{ $assignment->status === 'completed' ? 'success' : 'warning' }} ms-1">
                        {{ ucfirst($assignment->status ?? 'pending') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Abstract --}}
    <div class="card panel-card mb-4" data-aos="fade-up">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-text-paragraph me-2 text-primary"></i>Abstract</h6>
            <p class="small lh-lg mb-0">{!! nl2br(e($assignment->submission->abstract ?? '')) !!}</p>
        </div>
    </div>

    {{-- Manuscript Files --}}
    <div class="card panel-card mb-4" data-aos="fade-up">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-folder2-open me-2 text-primary"></i>Manuscript Files</h6>
            @forelse($assignment->submission->files ?? [] as $file)
            @php
                $iconMap = ['manuscript'   => ['bi-file-earmark-word', 'bg-primary-subtle text-primary'],
                            'cover_letter' => ['bi-file-earmark-text', 'bg-success-subtle text-success'],
                            'supplementary'=> ['bi-file-earmark-zip',  'bg-warning-subtle text-warning']];
                [$icon, $iconClass] = $iconMap[$file->file_type] ?? ['bi-file-earmark', 'bg-secondary-subtle text-secondary'];
            @endphp
            <div class="d-flex align-items-center justify-content-between py-2 px-2 file-row rounded-3 mb-1">
                <div class="d-flex align-items-center gap-3">
                    <div class="file-icon {{ $iconClass }}">
                        <i class="bi {{ $icon }}"></i>
                    </div>
                    <div>
                        <div class="fw-semibold small">{{ $file->original_name }}</div>
                        <div class="text-muted" style="font-size:.78rem">
                            {{ ucwords(str_replace('_',' ',$file->file_type)) }}
                            &middot; {{ number_format($file->file_size / 1024, 1) }} KB
                        </div>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#fileModal"
                        data-name="{{ $file->original_name }}"
                        data-type="{{ ucwords(str_replace('_',' ',$file->file_type)) }}"
                        data-size="{{ number_format($file->file_size / 1024, 1) }} KB"
                        data-date="{{ $file->created_at->format('d M Y, H:i') }}"
                        data-url="{{ route('reviewer.submissions.files.download', [$assignment->submission, $file]) }}">
                    <i class="bi bi-download me-1"></i>Download
                </button>
            </div>
            @empty
            <p class="text-muted small mb-0">No files attached to this submission.</p>
            @endforelse
        </div>
    </div>

    {{-- Submit Review --}}
    @if($assignment->status !== 'completed')
    <div class="card panel-card" data-aos="fade-up">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-clipboard-check me-2 text-primary"></i>Submit Review</h6>
            <form method="POST" action="{{ route('reviewer.reviews.store', $assignment->id) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Recommendation <span class="text-danger">*</span></label>
                    <select name="recommendation" class="form-select form-select-sm" required>
                        <option value="">Select recommendation</option>
                        <option value="accept">Accept</option>
                        <option value="minor_revision">Minor Revision</option>
                        <option value="major_revision">Major Revision</option>
                        <option value="reject">Reject</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Comments for the Author <span class="text-danger">*</span></label>
                    <textarea name="comments_for_author" rows="6" class="form-control form-control-sm"
                              placeholder="Your detailed comments for the author..." required></textarea>
                    <div class="form-text">These comments are shown to the author and form the basis of any revision request.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Confidential Comments for the Editor</label>
                    <textarea name="comments_for_editor" rows="3" class="form-control form-control-sm"
                              placeholder="Optional. Visible only to the editorial team, never to the author."></textarea>
                    <div class="form-text">Confidential: this section is never shown to the author.</div>
                </div>
                <button class="btn btn-primary fw-semibold">
                    <i class="bi bi-send me-2"></i>Submit Review
                </button>
            </form>
        </div>
    </div>
    @else
    <div class="alert alert-success rounded-4" data-aos="fade-up">
        <i class="bi bi-check-circle-fill me-2"></i>You have already submitted your review for this assignment.
    </div>
    @endif

</div>
@endsection

@push('modals')
<div class="modal fade" id="fileModal" tabindex="-1" aria-labelledby="fileModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="fileModalLabel">
                    <i class="bi bi-file-earmark-arrow-down me-2 text-primary"></i>Download File
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="bg-light rounded-3 p-4 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:48px;height:48px;border-radius:.75rem;background:#dbeafe;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0">
                            <i class="bi bi-file-earmark-text text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-semibold" id="modal-file-name"></div>
                            <div class="text-muted small" id="modal-file-meta"></div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-0">
                    <i class="bi bi-shield-lock me-1 text-success"></i>
                    Secure download — for review purposes only.
                </p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0 gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <a id="modal-download-btn" href="#" class="btn btn-primary btn-sm fw-semibold">
                    <i class="bi bi-download me-1"></i>Download File
                </a>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
    document.getElementById('fileModal').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('modal-file-name').textContent = btn.dataset.name;
        document.getElementById('modal-file-meta').textContent =
            btn.dataset.type + ' · ' + btn.dataset.size + ' · ' + btn.dataset.date;
        document.getElementById('modal-download-btn').href = btn.dataset.url;
    });
</script>
@endpush
