@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <span class="badge text-bg-light text-primary px-3 py-2 mb-2">Editorial workspace</span>
            <h3 class="fw-bold mb-1">{{ $submission->submission_no }}</h3>
            <p class="text-muted mb-0">{{ $submission->title }}</p>
        </div>
        <a href="{{ route('editor.workspace.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show rounded-4 mb-4">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger rounded-4 mb-4">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Reviewer recommendations (Module 0 finding B2: previously never displayed) --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-clipboard-check me-2 text-primary"></i>Reviewer reports</h6>
                        <span class="badge text-bg-light">{{ $summary['total'] }} submitted</span>
                    </div>

                    @if($reviews->isEmpty())
                        <p class="text-muted small mb-0">No reviewer has submitted a report yet.</p>
                    @else
                        <div class="row g-2 mb-3">
                            @foreach($summary['counts'] as $key => $count)
                                @if($count > 0)
                                    <div class="col-6 col-md-3">
                                        <div class="border rounded-3 p-2 text-center">
                                            <div class="fs-5 fw-bold">{{ $count }}</div>
                                            <div class="text-muted" style="font-size:.75rem">{{ $summary['labels'][$key] }}</div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @foreach($reviews as $review)
                            <div class="border rounded-3 p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="badge text-bg-{{ $review->recommendation === 'reject' ? 'danger' : ($review->recommendation === 'accept' ? 'success' : 'warning') }} me-1">
                                            {{ $review->recommendationLabel() }}
                                        </span>
                                        <span class="small text-muted">
                                            {{ $review->assignment?->reviewer?->name ?? 'Reviewer' }}
                                            · {{ $review->submitted_at?->format('d M Y') }}
                                        </span>
                                    </div>
                                    <span class="badge text-bg-light">Read-only</span>
                                </div>

                                <div class="small mb-2">
                                    <strong>For the author:</strong>
                                    <div class="text-muted" style="white-space: pre-line">{{ $review->comments_for_author }}</div>
                                </div>

                                @if($review->comments_for_editor)
                                    <details class="small">
                                        <summary class="text-muted">Confidential comments for the editor</summary>
                                        <div class="text-muted border-start ps-2 mt-1" style="white-space: pre-line">{{ $review->comments_for_editor }}</div>
                                    </details>
                                @endif
                            </div>
                        @endforeach

                        <div class="alert alert-light border small mb-0">
                            <i class="bi bi-lock me-1"></i>
                            Submitted reviews are part of the editorial record and cannot be edited by anyone,
                            including you. If a report is wrong, ask the reviewer to withdraw and re-submit.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Stage transition --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-diagram-3 me-2 text-primary"></i>Move to another stage</h6>

                    <p class="text-muted small">
                        Current stage:
                        <span class="badge {{ $stage?->badgeClass() ?? 'text-bg-secondary' }}">{{ $stage?->name ?? 'Not yet staged' }}</span>
                    </p>

                    @if($transitions === [])
                        <p class="text-muted small mb-0">No transitions are available to you from this stage.</p>
                    @else
                        <form method="POST" action="{{ route('editor.workspace.transition', $submission) }}">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold">Move to</label>
                                    <select name="to_stage" class="form-select form-select-sm" required>
                                        @foreach($transitions as $transition)
                                            <option value="{{ $transition->to_stage_slug }}">{{ $transition->label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold">Reason (recorded in the audit log)</label>
                                    <input type="text" name="reason" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-primary btn-sm w-100">Move</button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Publication readiness (§4: no publishing merely because editing is done) --}}
            <div class="card border-0 shadow-sm mb-4 {{ $readiness['ready'] ? 'border-success' : 'border-warning' }}">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">
                        <i class="bi {{ $readiness['ready'] ? 'bi-shield-check text-success' : 'bi-shield-exclamation text-warning' }} me-2"></i>
                        Publication readiness
                        @if($readiness['ready'])
                            <span class="badge text-bg-success ms-2">Ready</span>
                        @else
                            <span class="badge text-bg-warning ms-2">Not ready</span>
                        @endif
                    </h6>

                    <p class="text-muted small mb-2">
                        Publishing runs a readiness gate. Everything below must be true before the
                        production step accepts the manuscript.
                    </p>

                    <ul class="list-group list-group-flush">
                        @foreach($readiness['requirements'] as $requirement)
                            <li class="list-group-item px-2 d-flex justify-content-between align-items-start small">
                                <div>
                                    <span class="fw-semibold">{{ $requirement['name'] }}</span>
                                    <div class="text-muted">{{ $requirement['detail'] }}</div>
                                </div>
                                <i class="bi {{ $requirement['met'] ? 'bi-check-lg text-success' : 'bi-x-lg text-danger' }} mt-1"></i>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Audit --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent activity</h6>
                        <a href="{{ route('editor.workspace.audit', $submission) }}" class="btn btn-outline-secondary btn-sm">
                            Full audit history
                        </a>
                    </div>

                    @forelse(array_slice($history, 0, 8) as $event)
                        <div class="d-flex justify-content-between small border-bottom py-2">
                            <span>{{ str_replace('_', ' ', $event->event) }}</span>
                            <span class="text-muted">
                                {{ $event->actor?->name ?? 'System' }} ·
                                {{ $event->created_at?->diffForHumans() }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No recorded activity yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Decision --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-gavel me-2 text-primary"></i>Record a decision</h6>

                    <form method="POST" action="{{ route('editor.workspace.decision', $submission) }}">
                        @csrf
                        <div class="mb-2">
                            <select name="decision" class="form-select form-select-sm" required>
                                <option value="revision_requested">Request revision</option>
                                <option value="accepted">Accept</option>
                                <option value="rejected">Reject</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Message to the author</label>
                            <textarea name="message" rows="4" class="form-control form-control-sm" required></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Revision window (days)</label>
                            <input type="number" name="revision_days" value="30" min="1" max="365" class="form-control form-control-sm">
                        </div>
                        <button class="btn btn-primary btn-sm w-100">Record decision</button>
                    </form>
                </div>
            </div>

            {{-- Assign reviewer --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-plus me-2 text-primary"></i>Invite a reviewer</h6>

                    <form method="POST" action="{{ route('editor.workspace.assign', $submission) }}">
                        @csrf
                        <div class="mb-2">
                            <select name="reviewer_id" class="form-select form-select-sm" required>
                                <option value="">Select reviewer</option>
                                @foreach($reviewers as $reviewer)
                                    <option value="{{ $reviewer->id }}">{{ $reviewer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Review due</label>
                            <input type="date" name="due_date" value="{{ now()->addDays(21)->toDateString() }}" class="form-control form-control-sm">
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="blinded" value="1" id="blinded" class="form-check-input" checked>
                            <label class="form-check-label small" for="blinded">Send a blinded copy</label>
                        </div>
                        <button class="btn btn-outline-primary btn-sm w-100">Invite</button>
                    </form>
                </div>
            </div>

            {{-- Blinded copy --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-eye-slash me-2 text-primary"></i>Blinded review copy</h6>
                    <p class="text-muted small">
                        Prepare the anonymised copy and review exactly what was removed before it is sent.
                    </p>
                    <a href="{{ route('editor.workspace.blinded', $submission) }}" class="btn btn-outline-secondary btn-sm">
                        Prepare and inspect
                    </a>
                </div>
            </div>

            {{-- Manuscript --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-file-ear me-2 text-primary"></i>Manuscript</h6>
                    <dl class="row small mb-3">
                        <dt class="col-5 text-muted">Author</dt>
                        <dd class="col-7">{{ $submission->author?->name }}</dd>
                        <dt class="col-5 text-muted">Assigned editor</dt>
                        <dd class="col-7">{{ $submission->assignedEditor?->name ?? 'Unassigned' }}</dd>
                        <dt class="col-5 text-muted">Round</dt>
                        <dd class="col-7">{{ $submission->revision_round }}</dd>
                        <dt class="col-5 text-muted">Due</dt>
                        <dd class="col-7">{{ $submission->decision_due_at?->format('d M Y') ?? '—' }}</dd>
                    </dl>

                    <a href="{{ route('workspace.show', $submission) }}" class="btn btn-outline-primary btn-sm w-100">
                        Open the workspace
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection