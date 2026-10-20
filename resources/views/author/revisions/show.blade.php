@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-warning px-3 py-2 mb-2">Revision</span>
            <h3 class="fw-bold mb-1">{{ $submission->submission_no }}</h3>
            <p class="text-muted mb-0">{{ $submission->title }}</p>
        </div>
        <div class="d-flex gap-2">
            @if($submission->current_stage === 'proof_approval')
                <a href="{{ route('author.proofs.show', $submission) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-file-earmark-check me-1"></i>Review and approve the proofs
                </a>
            @endif
            <a href="{{ route('author.revisions.compare', $submission) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-columns-gap me-1"></i>Compare versions
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-gavel me-2 text-primary"></i>Editorial decisions</h6>

                    @if($decisions->isEmpty())
                        <p class="text-muted small mb-0">No decision has been communicated yet.</p>
                    @endif

                    @foreach($decisions as $decision)
                        <div class="border rounded-3 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge text-bg-{{ $decision->decision === 'rejected' ? 'danger' : ($decision->decision === 'accepted' ? 'success' : 'warning') }}">
                                    {{ [
                                        'accepted' => 'Accepted',
                                        'rejected' => 'Declined',
                                        'revision_requested' => 'Revision requested',
                                    ][$decision->decision] ?? $decision->decision }}
                                </span>
                                <span class="small text-muted">{{ $decision->created_at->format('d M Y') }}</span>
                            </div>
                            @if($decision->message)
                                <div class="small" style="white-space: pre-line">{{ $decision->message }}</div>
                            @endif
                        </div>
                    @endforeach

                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-lock me-1"></i>
                        Decisions are the editors' record. You can read them here, but they cannot be altered
                        from the author side.
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-word me-2 text-primary"></i>Your versions</h6>

                    @if($versions->isEmpty())
                        <p class="text-muted small mb-0">No manuscript versions are on record yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Version</th><th>Type</th><th>Created</th><th></th></tr></thead>
                                <tbody>
                                    @foreach($versions as $version)
                                        <tr>
                                            <td class="small fw-semibold">v{{ $version->version_number ?? $version->getKey() }}</td>
                                            <td class="small text-muted">{{ $version->origin ?? '—' }}</td>
                                            <td class="small text-muted">{{ $version->created_at?->format('d M Y H:i') }}</td>
                                            <td class="text-end">
                                                @if($version->document)
                                                    <a href="{{ route('workspace.show', $submission) }}" class="btn btn-sm btn-outline-secondary">Open in the workspace</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3 {{ $canRespond ? 'border-primary' : '' }}">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-chat-dots me-2 text-primary"></i>Revision window</h6>

                    @if($round)
                        <dl class="row small mb-3">
                            <dt class="col-5 text-muted">Round</dt>
                            <dd class="col-7">{{ $round->round_number }}</dd>

                            <dt class="col-5 text-muted">Opened</dt>
                            <dd class="col-7">{{ $round->opened_at?->format('d M Y') }}</dd>

                            <dt class="col-5 text-muted">Due</dt>
                            <dd class="col-7 {{ $round->isOverdue() ? 'text-danger fw-semibold' : '' }}">
                                {{ $round->due_at?->format('d M Y') ?? 'No deadline set' }}
                                @if($round->isOverdue())<span class="ms-1">· overdue</span>@endif
                            </dd>

                            <dt class="col-5 text-muted">Status</dt>
                            <dd class="col-7">{{ ucfirst($round->status) }}</dd>
                        </dl>

                        @if($round->author_response)
                            <div class="alert alert-success small">
                                <strong>Your response is on record.</strong>
                                <div class="mt-1" style="white-space: pre-line">{{ Str::limit($round->author_response, 220) }}</div>
                            </div>
                        @elseif($canRespond)
                            <form method="POST" action="{{ route('author.revisions.respond', $submission) }}">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Respond to the editor</label>
                                    <textarea name="response" rows="6" class="form-control form-control-sm"
                                              placeholder="Explain what you have changed, and where."></textarea>
                                </div>
                                <button class="btn btn-primary btn-sm w-100">Send response</button>
                            </form>
                        @elseif($submission->isRevisionWindowOpen())
                            <div class="alert alert-info small mb-0">The window is open; upload the revised file from the submission area.</div>
                        @else
                            <div class="alert alert-secondary small mb-0">This revision round is closed.</div>
                        @endif
                    @else
                        <p class="text-muted small mb-0">No revision round has been opened for this manuscript.</p>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>Status</h6>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted">Status</dt>
                        <dd class="col-7">{{ $submission->status }}</dd>

                        <dt class="col-5 text-muted">Stage</dt>
                        <dd class="col-7">{{ $submission->currentStageName() }}</dd>

                        <dt class="col-5 text-muted">Round</dt>
                        <dd class="col-7">{{ $submission->revision_round }}</dd>

                        <dt class="col-5 text-muted">Deadline</dt>
                        <dd class="col-7">{{ $submission->decision_due_at?->format('d M Y') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
