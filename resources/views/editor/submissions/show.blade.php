@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .meta-badge { border-radius: 999px; padding: .45rem .9rem; font-size: .82rem; }
    .file-row { transition: background .15s; }
    .file-row:hover { background: #f1f5f9; }
    .file-icon { width: 40px; height: 40px; border-radius: .75rem; display:flex; align-items:center; justify-content:center; font-size: 1.1rem; flex-shrink:0; }
</style>

<div class="container py-4">
    @php
        $isAdminSubmissionRoute = request()->routeIs('admin.*');
        $backRoute = $isAdminSubmissionRoute ? route('admin.dashboard') : route('editor.submissions.index');
        $downloadRouteName = $isAdminSubmissionRoute ? 'admin.submissions.files.download' : 'editor.submissions.files.download';
        $manuscriptFile = $submission->files->firstWhere('file_type', 'manuscript');
        $hasPaidVetting = $submission->payments->where('purpose', 'vetting_fee')->where('status', 'paid')->isNotEmpty();
        $hasPaidPublication = $submission->payments->where('purpose', 'publication_fee')->where('status', 'paid')->isNotEmpty();
        $latestVettingPayment = $submission->payments->where('purpose', 'vetting_fee')->sortByDesc('created_at')->first();
        $latestPublicationPayment = $submission->payments->where('purpose', 'publication_fee')->sortByDesc('created_at')->first();
        $canManuallyApprovePayment = auth()->user()->hasAnyRole(['Admin', 'Managing Editor', 'Super Admin']);
        $canAssignReviewer = $hasPaidVetting && in_array($submission->status, ['submitted', 'revision_requested'], true);
    @endphp

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div>
            <h3 class="fw-bold mb-1">Submission Details</h3>
            <p class="text-muted mb-0 small">Editorial overview and action controls for this manuscript.</p>
        </div>
        <a href="{{ $backRoute }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4">
        {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show rounded-4 mb-4">
        {{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if($errors->any())
    <div class="alert alert-danger rounded-4 mb-4">
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <div class="row g-4">

        {{-- Left column --}}
        <div class="col-lg-8">

            {{-- Manuscript info --}}
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-light meta-badge">{{ $submission->submission_no }}</span>
                        @php
                            $colors = ['pending_payment'=>'warning','submitted'=>'primary','under_review'=>'info','revision_requested'=>'warning',
                                       'accepted'=>'success','rejected'=>'danger','published'=>'dark'];
                            $color = $colors[$submission->status] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $color }} meta-badge">{{ ucwords(str_replace('_',' ',$submission->status)) }}</span>
                        @if($submission->subject_area)
                        <span class="badge text-bg-warning meta-badge">{{ $submission->subject_area }}</span>
                        @endif
                    </div>
                    <h4 class="fw-bold mb-3">{{ $submission->title }}</h4>
                    <div class="row g-2 text-muted small">
                        <div class="col-md-6"><i class="bi bi-person me-1"></i><strong>Author:</strong> {{ $submission->author->name ?? 'N/A' }}</div>
                        <div class="col-md-6"><i class="bi bi-envelope me-1"></i>{{ $submission->author->email ?? '' }}</div>
                        <div class="col-md-6"><i class="bi bi-calendar me-1"></i><strong>Submitted:</strong> {{ $submission->created_at?->format('d M Y') }}</div>
                        @if($submission->keywords)
                        <div class="col-12"><i class="bi bi-tags me-1"></i><strong>Keywords:</strong> {{ $submission->keywords }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Abstract --}}
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-text-paragraph me-2 text-primary"></i>Abstract</h6>
                    <p class="mb-0 small lh-lg">{!! nl2br(e($submission->abstract)) !!}</p>
                </div>
            </div>

            {{-- Uploaded Files --}}
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-folder2-open me-2 text-primary"></i>Uploaded Files</h6>
                    @if($isAdminSubmissionRoute && !$manuscriptFile)
                    <div class="alert alert-warning border rounded-3 small mb-3">
                        The manuscript file is missing for this submission. Use the admin recovery form below to upload the author's copy and restore download access.
                    </div>
                    @endif
                    @forelse($submission->files as $file)
                    @php
                        $iconMap = ['manuscript'=>['bi-file-earmark-word','bg-primary-subtle text-primary'],
                                    'cover_letter'=>['bi-file-earmark-text','bg-success-subtle text-success'],
                                    'supplementary'=>['bi-file-earmark-zip','bg-warning-subtle text-warning']];
                        [$icon, $iconClass] = $iconMap[$file->file_type] ?? ['bi-file-earmark','bg-secondary-subtle text-secondary'];
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
                                    &middot; {{ $file->created_at->format('d M Y') }}
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
                                data-url="{{ route($downloadRouteName, [$submission, $file]) }}">
                            <i class="bi bi-download me-1"></i>Download
                        </button>
                    </div>
                    @empty
                    <p class="text-muted small mb-0">No files uploaded for this submission.</p>
                    @endforelse

                    {{-- Module 2 browser workspace open (authorisation is
                         enforced server-side by WorkspacePolicy; this link
                         is a convenience). --}}
                    @php($editorialRoles = auth()->user()?->hasAnyRole(['Super Admin','Admin','Managing Editor','Managing Admin','Editor']))
                    <div class="d-flex align-items-center justify-content-between py-3 px-2 rounded-3 mt-2 {{ $editorialRoles ? 'border' : 'd-none' }}">
                        <div>
                            <div class="fw-semibold small">
                                <i class="bi bi-pencil-square me-1 text-primary"></i>Browser manuscript workspace
                            </div>
                            <div class="text-muted" style="font-size:.78rem">
                                Edit, comment and track changes in the browser — sequential editing with save-conflict detection, never silent overwrites.
                            </div>
                        </div>
                        <a href="{{ route('workspace.show', $submission) }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Open workspace
                        </a>
                    </div>
                </div>
            </div>

            @if($isAdminSubmissionRoute)
            <div class="card panel-card mb-4" data-aos="fade-up" id="manuscript-recovery">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-life-preserver me-2 text-primary"></i>Recover Missing Manuscript</h6>
                    <p class="text-muted small mb-3">
                        Use this only when the author already submitted the manuscript, but the stored file is missing from the platform. The recovered file will be attached to this submission and restore secure downloads.
                    </p>
                    @if($manuscriptFile)
                    <div class="alert alert-info border rounded-3 small mb-3">
                        A manuscript is already attached: <strong>{{ $manuscriptFile->original_name }}</strong>. Upload a replacement only if the current file is wrong or unreadable.
                    </div>
                    @endif
                    <form method="POST" action="{{ route('admin.submissions.files.recover', $submission) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Recovered Manuscript</label>
                            <input type="file" name="manuscript" class="form-control form-control-sm" accept=".pdf,.doc,.docx" required>
                            <div class="form-text">Accepted formats: PDF, DOC, DOCX up to 1 GB.</div>
                        </div>
                        @if($manuscriptFile)
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="replace_existing" name="replace_existing">
                            <label class="form-check-label small" for="replace_existing">
                                Replace the current manuscript record with this recovered file
                            </label>
                        </div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Recovery Note</label>
                            <textarea name="note" rows="3" class="form-control form-control-sm" placeholder="Example: Author resent manuscript after original storage record was missing"></textarea>
                        </div>
                        <button class="btn btn-outline-primary btn-sm w-100 fw-semibold">
                            <i class="bi bi-upload me-1"></i>Recover Manuscript File
                        </button>
                    </form>
                </div>
            </div>
            @endif

            {{-- Co-Authors --}}
            @if($submission->coAuthors->count())
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-people me-2 text-primary"></i>Co-Authors</h6>
                    <div class="row g-3">
                        @foreach($submission->coAuthors as $ca)
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3">
                                <div class="fw-semibold small">{{ $ca->full_name }}</div>
                                <div class="text-muted" style="font-size:.8rem">{{ $ca->email ?? '' }}</div>
                                <div class="text-muted" style="font-size:.8rem">{{ $ca->institution ?? '' }}{{ $ca->country ? ', '.$ca->country : '' }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- Review assignments --}}
            @if($submission->reviewerAssignments->count())
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clipboard-check me-2 text-primary"></i>Review Assignments</h6>
                    @foreach($submission->reviewerAssignments as $assignment)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="fw-semibold small">{{ $assignment->reviewer->name ?? 'N/A' }}</div>
                            <div class="text-muted" style="font-size:.78rem">Due: {{ $assignment->due_date?->format('d M Y') ?? 'N/A' }}</div>
                        </div>
                        <span class="badge bg-{{ $assignment->status === 'completed' ? 'success' : 'warning' }}">
                            {{ ucfirst($assignment->status) }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Reviewer reports (Module 0 finding B2: these were previously written but never rendered) --}}
            @php $submittedReviews = $submission->reviewerAssignments->filter(fn ($a) => $a->review !== null && $a->review->status === 'submitted'); @endphp
            @if($submittedReviews->isNotEmpty())
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-chat-square-text me-2 text-primary"></i>Reviewer Reports</h6>

                    @foreach($submittedReviews as $assignment)
                        <div class="border rounded-3 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="badge bg-{{ $assignment->review->recommendation === 'reject' ? 'danger' : ($assignment->review->recommendation === 'accept' ? 'success' : 'warning') }} me-1">
                                        {{ $assignment->review->recommendationLabel() }}
                                    </span>
                                    <span class="text-muted" style="font-size:.8rem">
                                        {{ $assignment->reviewer->name }} ·
                                        {{ $assignment->review->submitted_at?->format('d M Y, H:i') }}
                                    </span>
                                </div>
                                <span class="badge bg-light text-dark">Read-only</span>
                            </div>

                            <div class="small mb-2">
                                <strong>For the author:</strong>
                                <div class="text-muted" style="white-space: pre-line">{{ $assignment->review->comments_for_author }}</div>
                            </div>

                            @if($assignment->review->comments_for_editor)
                                <details class="small">
                                    <summary class="text-muted">Confidential comments for the editor</summary>
                                    <div class="text-muted border-start ps-2 mt-1" style="white-space: pre-line">{{ $assignment->review->comments_for_editor }}</div>
                                </details>
                            @endif
                        </div>
                    @endforeach

                    <div class="alert alert-light border rounded-3 small mb-0">
                        <i class="bi bi-lock me-1"></i>
                        Submitted reviews are part of the editorial record and cannot be edited by anyone,
                        including editors. If a report needs correcting, ask the reviewer to withdraw and re-submit.
                    </div>
                </div>
            </div>
            @endif

        </div>

        {{-- Right column: Editorial Actions --}}
        <div class="col-lg-4">

            {{-- Assign Reviewer --}}
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-check me-2 text-primary"></i>Assign Reviewer</h6>
                    @if(!$canAssignReviewer)
                    <div class="alert alert-warning border rounded-3 small mb-0">
                        Reviewer assignment is locked until the vetting fee is confirmed and the submission leaves the <strong>pending payment</strong> state.
                    </div>
                    @else
                    <form method="POST" action="{{ route('editor.submissions.assignReviewer', $submission) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Reviewer</label>
                            <select name="reviewer_id" class="form-select form-select-sm" required>
                                <option value="">Select reviewer</option>
                                @foreach($reviewers as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Due Date</label>
                            <input type="date" name="due_date" class="form-control form-control-sm"
                                   value="{{ now()->addDays(14)->toDateString() }}">
                        </div>
                        <button class="btn btn-primary btn-sm w-100">Assign Reviewer</button>
                    </form>
                    @endif
                </div>
            </div>

            <div class="card panel-card mb-4" data-aos="fade-up" id="payment-actions">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-credit-card-2-front me-2 text-primary"></i>Payment Status</h6>
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold small">Vetting Fee</div>
                                <div class="text-muted small">
                                    {{ $hasPaidVetting ? 'Paid' : 'Not yet recorded' }}
                                </div>
                            </div>
                            <span class="badge bg-{{ $hasPaidVetting ? 'success' : 'warning' }}">
                                {{ $hasPaidVetting ? 'Paid' : 'Pending' }}
                            </span>
                        </div>
                        @if($latestVettingPayment)
                        <div class="text-muted small mt-2">
                            Ref: {{ $latestVettingPayment->reference }} | Status: {{ ucfirst($latestVettingPayment->status) }}
                        </div>
                        @endif
                    </div>

                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold small">Publication Fee</div>
                                <div class="text-muted small">
                                    {{ $hasPaidPublication ? 'Paid' : 'Not yet recorded' }}
                                </div>
                            </div>
                            <span class="badge bg-{{ $hasPaidPublication ? 'success' : 'warning' }}">
                                {{ $hasPaidPublication ? 'Paid' : 'Pending' }}
                            </span>
                        </div>
                        @if($latestPublicationPayment)
                        <div class="text-muted small mt-2">
                            Ref: {{ $latestPublicationPayment->reference }} | Status: {{ ucfirst($latestPublicationPayment->status) }}
                        </div>
                        @endif
                    </div>

                    @if($canManuallyApprovePayment)
                    <div class="alert alert-light border rounded-3 small mb-3">
                        Use manual approval only when gateway verification or callback handling failed, but you have already confirmed the payment externally.
                    </div>
                    <form method="POST" action="{{ route('admin.submissions.payments.manual', $submission) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Payment Purpose</label>
                            <select name="purpose" class="form-select form-select-sm" required>
                                <option value="">Select payment</option>
                                @unless($hasPaidVetting)
                                <option value="vetting_fee">Vetting Fee</option>
                                @endunless
                                @unless($hasPaidPublication)
                                <option value="publication_fee">Publication Fee</option>
                                @endunless
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Verified Reference</label>
                            <input type="text" name="reference" class="form-control form-control-sm" placeholder="Bank transfer ref, Paystack ref, teller no">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold">Amount</label>
                                <input type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" placeholder="Auto if blank">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold">Paid At</label>
                                <input type="datetime-local" name="paid_at" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Admin Note</label>
                            <textarea name="note" rows="3" class="form-control form-control-sm" placeholder="Why manual approval was needed"></textarea>
                        </div>
                        <button class="btn btn-outline-success btn-sm w-100 fw-semibold">
                            <i class="bi bi-shield-check me-1"></i>Record Manual Payment
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            {{-- Editorial Decision --}}
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-gavel me-2 text-primary"></i>Editorial Decision</h6>
                    <form method="POST" action="{{ route('editor.submissions.decision', $submission) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Decision</label>
                            <select name="decision" class="form-select form-select-sm" required>
                                <option value="">Select decision</option>
                                <option value="revision_requested">Request Revision</option>
                                <option value="accepted">Accept</option>
                                <option value="rejected">Reject</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Message to Author</label>
                            <textarea name="message" rows="4" class="form-control form-control-sm"
                                      placeholder="Remarks or instructions for the author..."></textarea>
                        </div>
                        <button class="btn btn-dark btn-sm w-100">Submit Decision</button>
                    </form>
                </div>
            </div>

            {{-- Publish --}}
            @if(in_array($submission->status, ['screening','published']))
            <div class="card panel-card border-success" style="border:2px solid #198754 !important" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-2"><i class="bi bi-send-check me-2 text-success"></i>Publish to Issue</h6>
                    @if($submission->status === 'screening')
                    <p class="text-muted small mb-3">
                        Assigned to: <strong>{{ $submission->assignedEditor->name ?? 'N/A' }}</strong>
                    </p>
                    <a href="{{ route('editor.submissions.publish.form', $submission) }}"
                       class="btn btn-success btn-sm w-100">
                        <i class="bi bi-journal-arrow-up me-1"></i>Publish Article
                    </a>
                    @else
                    <span class="badge bg-dark">Published</span>
                    @endif
                </div>
            </div>
            @endif

        </div>
    </div>
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
                        <div class="file-icon bg-primary-subtle text-primary" style="width:48px;height:48px;border-radius:.75rem;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div>
                            <div class="fw-semibold" id="modal-file-name"></div>
                            <div class="text-muted small" id="modal-file-meta"></div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-0">
                    <i class="bi bi-shield-lock me-1 text-success"></i>
                    This file is stored securely. Download is logged for audit purposes.
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
