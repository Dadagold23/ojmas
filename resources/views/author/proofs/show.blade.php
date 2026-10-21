@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-primary px-3 py-2 mb-2">Proofs</span>
            <h3 class="fw-bold mb-1">{{ $submission->submission_no }}</h3>
            <p class="text-muted mb-0">{{ $submission->title }}</p>
        </div>
        <a href="{{ route('author.revisions.show', $submission) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Revision record
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success rounded-4 mb-4">{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-check me-2 text-primary"></i>Your proofs</h6>

                    <p class="small text-muted">
                        These are the final formatted copies of your manuscript. Please read every page —
                        your approval is what allows the article to proceed to production.
                    </p>

                    <div class="list-group list-group-flush mb-3">
                        @forelse($submission->files as $file)
                            <div class="list-group-item px-2 d-flex justify-content-between align-items-center">
                                <div class="small">
                                    <div class="fw-semibold">{{ $file->original_name }}</div>
                                    <div class="text-muted">{{ number_format($file->file_size / 1024, 0) }} kB
                                        @if($file->is_anonymised)<span>· anonymised copy</span>@endif
                                    </div>
                                </div>
                                <a href="{{ route('author.manuscripts.download', $submission) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-download me-1"></i>Download
                                </a>
                            </div>
                        @empty
                            <div class="list-group-item px-2 small text-muted">No files are on record.</div>
                        @endforelse
                    </div>

                    <a href="{{ route('workspace.show', $submission) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-columns-gap me-1"></i>Open the full workspace for version history
                    </a>

                    <div class="alert alert-light border rounded-3 small mb-0 mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Approval covers what is in the file: text, figures, tables, author details and references.
                        Later corrections require new proofs.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            {{-- Precondition checks — shown honestly as True/False; the
                 approval form stays locked while either is False. --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-check me-2 text-primary"></i>Before you can approve</h6>

                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <span class="small">Peer review concluded</span>
                        <span class="badge {{ $proofChecks['peer_review_concluded'] ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $proofChecks['peer_review_concluded'] ? 'True' : 'False' }}
                        </span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Copyediting completed</span>
                        <span class="badge {{ $proofChecks['copyediting_completed'] ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $proofChecks['copyediting_completed'] ? 'True' : 'False' }}
                        </span>
                    </div>

                    @if($proofGateSatisfied)
                        <div class="small text-success">
                            <i class="bi bi-check2-circle me-1"></i>
                            Both checks are satisfied — you may record your decision below.
                        </div>
                    @else
                        <div class="small text-muted">
                            <i class="bi bi-lock me-1"></i>
                            The approval gate is not satisfied yet. Ask the editorial team once both checks show True;
                            this form stays harmless (no approval can be recorded) until then.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-primary border-3 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-pen me-2 text-primary"></i>Your decision</h6>

                    @if($errors->any() && $errors->has('proofs'))
                        <div class="alert alert-warning rounded-3 small">{{ $errors->first('proofs') }}</div>
                    @endif

                    <form method="POST" action="{{ route('author.proofs.approve', $submission) }}">
                        @csrf

                        @unless($proofGateSatisfied)
                            <fieldset disabled aria-disabled="true">
                                <div class="alert alert-light border small mb-3">
                                    <i class="bi bi-lock-fill me-1"></i>
                                    Locked while the checks above show False.
                                </div>
                        @endunless

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="status" id="proof-approve"
                                   value="approved" checked required>
                            <label class="form-check-label small fw-semibold" for="proof-approve">
                                I approve these proofs for publication
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="status" id="proof-corrections"
                                   value="corrections_requested" required>
                            <label class="form-check-label small fw-semibold" for="proof-corrections">
                                I request corrections
                            </label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="proof-notes">Notes for the editor (optional)</label>
                            <textarea name="notes" id="proof-notes" rows="4" class="form-control form-control-sm"
                                      placeholder="Where corrections are needed — or anything the production team should know."></textarea>
                        </div>

                        <button class="btn btn-primary btn-sm w-100">Submit approval decision</button>

                        @unless($proofGateSatisfied)
                            </fieldset>
                        @endunless
                    </form>
                </div>
            </div>

            @if($approvals->isNotEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-muted"></i>Previous approvals</h6>

                        @foreach($approvals as $approval)
                            <div class="border-bottom pb-2 mb-2 small">
                                <span class="badge {{ $approval->status === 'approved' ? 'text-bg-success' : 'text-bg-warning' }} me-1">
                                    {{ $approval->status === 'approved' ? 'Approved' : 'Corrections requested' }}
                                </span>
                                <span class="text-muted">{{ $approval->approved_at?->format('d M Y H:i') ?? $approval->created_at->format('d M Y H:i') }}</span>
                                @if($approval->notes)
                                    <div class="text-muted">{{ \Illuminate\Support\Str::limit($approval->notes, 140) }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
