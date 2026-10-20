@extends('layouts.app')

@section('content')
<div id="app"
     data-save-url="{{ route('workspace.save', $submission) }}"
     data-document-url="{{ route('workspace.document', $submission) }}"
     data-comment-url="{{ route('workspace.comments.store', $submission) }}"
     data-comment-status-url="{{ route('workspace.comments.status', [$submission, '__ID__']) }}"
     data-change-store-url="{{ route('workspace.changes.store', $submission) }}"
     data-change-resolve-url="{{ route('workspace.changes.resolve', $submission) }}"
     data-versions-url="{{ route('workspace.versions', $submission) }}"
     data-version-save-url="{{ route('workspace.versions.save', $submission) }}"
     data-compare-url="{{ route('workspace.versions.compare', [$submission, '__LEFT__', '__RIGHT__']) }}"
     data-restore-url="{{ route('workspace.versions.restore', [$submission, '__ID__']) }}"
     data-export-url="{{ route('workspace.export', $submission) }}"
     data-can-edit="{{ $capabilities['canEdit'] ? '1' : '0' }}"
     data-can-comment="{{ $capabilities['canComment'] ? '1' : '0' }}"
     data-can-review-changes="{{ $capabilities['canReviewChanges'] ? '1' : '0' }}"
     data-can-restore="{{ $capabilities['canRestore'] ? '1' : '0' }}"
     data-can-compare="{{ $capabilities['canCompare'] ? '1' : '0' }}"
     data-can-export="{{ $capabilities['canExport'] ? '1' : '0' }}"
     data-can-force-save="{{ $capabilities['canForceSave'] ? '1' : '0' }}">

    <div class="container-fluid py-4 px-lg-4">

        {{-- Header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge text-bg-light text-primary">Manuscript workspace</span>
                    <span class="badge text-bg-light text-primary">{{ $submission->submission_no }}</span>
                    <span class="badge text-bg-secondary">{{ ucfirst($submission->status) }}</span>
                    <span class="badge text-bg-light" id="revision-label">
                        Revision {{ $workspace?->revision ?? 1 }}
                    </span>
                </div>
                <h4 class="fw-bold mb-0">{{ $submission->title }}</h4>
                <p class="text-muted small mb-0">
                    Editing in the browser. The original uploaded file is unchanged and is kept separately.
                </p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <span id="save-status" class="badge text-bg-light" data-state="loading">Loading…</span>
                <button id="btn-save-now" class="btn btn-primary btn-sm" @disabled(!$capabilities['canEdit'])>
                    <i class="bi bi-cloud-arrow-up me-1"></i>Save now
                </button>
                <button id="btn-export" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-word me-1"></i>Export DOCX
                </button>
            </div>
        </div>

        @if($workspace === null)
            <div class="alert alert-warning rounded-4 py-2" role="alert">
                <div class="fw-semibold mb-1">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    The browser editor could not open this manuscript.
                </div>
                <p class="small mb-1">There is no readable document version behind this submission yet. That is normally because:</p>
                <ul class="small mb-1">
                    <li>the original upload is not a readable <code>.docx</code> file (for example a PDF or a corrupt archive), and the editor never opened a DOCX version for it; or</li>
                    <li>no manuscript file is attached to the submission at all.</li>
                </ul>
                <p class="small mb-0">
                    Nothing was changed. The original upload and the APA 7 formatting tools remain available for this submission.
                </p>
            </div>
        @endif

        @if(!$capabilities['canEdit'])
            <div class="alert alert-info rounded-4 py-2 small" id="editor-notice">
                <i class="bi bi-eye me-1"></i>
                You have read access to this manuscript. Editing is restricted to the editorial team and,
                for the submitting author, while a revision is open.
            </div>
        @endif

        <div class="row g-3">

            {{-- Editor column --}}
            <div class="col-lg-7 col-xl-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">

                        <div class="btn-toolbar gap-1 border rounded-3 p-2 mb-2 flex-wrap">
                            <button id="btn-bold" class="btn btn-sm btn-outline-secondary" type="button"><strong>B</strong></button>
                            <button id="btn-italic" class="btn btn-sm btn-outline-secondary" type="button"><em>I</em></button>
                            <span class="vr mx-1"></span>
                            @foreach([1, 2, 3] as $level)
                                <button id="btn-h{{ $level }}" class="btn btn-sm btn-outline-secondary" type="button">H{{ $level }}</button>
                            @endforeach
                            <span class="vr mx-1"></span>

                            {{-- Paragraph and typography controls (office-suite style).
                                 APA 7 defaults are preselected; any value explicitly
                                 chosen here is a deliberate editorial decision and is
                                 carried faithfully into the exported DOCX. --}}
                            <select id="sel-font-family" class="form-select form-select-sm d-inline-flex" style="width:auto; min-width:11rem;" title="Font family">
                                <option value="">Font: APA default (Times New Roman)</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Cambria">Cambria</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Garamond">Garamond</option>
                                <option value="Arial">Arial</option>
                                <option value="Calibri">Calibri</option>
                                <option value="Verdana">Verdana</option>
                                <option value="Courier New">Courier New</option>
                            </select>
                            <select id="sel-font-size" class="form-select form-select-sm d-inline-flex" style="width:auto; min-width:6.5rem;" title="Font size">
                                <option value="">Size: APA default (12 pt)</option>
                                @foreach(['10pt', '11pt', '12pt', '13pt', '14pt', '16pt', '18pt', '20pt', '24pt', '48pt'] as $pt)
                                    <option value="{{ $pt }}">{{ (int) $pt }} pt</option>
                                @endforeach
                            </select>
                            <select id="sel-line-spacing" class="form-select form-select-sm d-inline-flex" style="width:auto; min-width:9rem;" title="Line spacing">
                                <option value="">Spacing: APA default (double)</option>
                                <option value="1">Single (1.0)</option>
                                <option value="1.15">1.15</option>
                                <option value="1.5">1.5</option>
                                <option value="2">Double (2.0)</option>
                                <option value="3">Triple (3.0)</option>
                            </select>
                            <select id="sel-space-before" class="form-select form-select-sm d-inline-flex" style="width:auto; min-width:8rem;" title="Space before paragraph">
                                <option value="">Before: APA default (0 pt)</option>
                                <option value="0">Before: 0 pt</option>
                                @foreach([3, 6, 12, 18, 24] as $pt)
                                    <option value="{{ $pt }}">Before: {{ $pt }} pt</option>
                                @endforeach
                            </select>
                            <select id="sel-space-after" class="form-select form-select-sm d-inline-flex" style="width:auto; min-width:8rem;" title="Space after paragraph">
                                <option value="">After: APA default (0 pt)</option>
                                <option value="0">After: 0 pt</option>
                                @foreach([3, 6, 12, 18, 24] as $pt)
                                    <option value="{{ $pt }}">After: {{ $pt }} pt</option>
                                @endforeach
                            </select>
                            <select id="sel-first-line-indent" class="form-select form-select-sm d-inline-flex" style="width:auto; min-width:9.5rem;" title="First-line indent">
                                <option value="">Indent: APA default (0.5&#8221;)</option>
                                <option value="0">Flush left (0&#8221;)</option>
                                <option value="360">0.25&#8221;</option>
                                <option value="720">0.5&#8221;</option>
                                <option value="1080">0.75&#8221;</option>
                                <option value="1440">1&#8221;</option>
                            </select>
                            <span class="vr mx-1"></span>
                            <button id="btn-align-left" class="btn btn-sm btn-outline-secondary" type="button" title="Align left (APA default)"><i class="bi bi-text-left"></i></button>
                            <button id="btn-align-center" class="btn btn-sm btn-outline-secondary" type="button" title="Align centre"><i class="bi bi-text-center"></i></button>
                            <button id="btn-align-right" class="btn btn-sm btn-outline-secondary" type="button" title="Align right"><i class="bi bi-text-right"></i></button>
                            <button id="btn-align-justify" class="btn btn-sm btn-outline-secondary" type="button" title="Justify"><i class="bi bi-justify"></i></button>
                            <span class="vr mx-1"></span>
                            <button id="btn-undo" class="btn btn-sm btn-outline-secondary" type="button"><i class="bi bi-arrow-counterclockwise"></i></button>
                            <button id="btn-redo" class="btn btn-sm btn-outline-secondary" type="button"><i class="bi bi-arrow-clockwise"></i></button>
                            <span class="vr mx-1"></span>
                            <button id="btn-comment" class="btn btn-sm btn-outline-primary" type="button" disabled>
                                <i class="bi bi-chat-left-text me-1"></i>Comment on selection
                            </button>
                        </div>

                        <div class="text-muted small mb-2" id="selection-preview"></div>

                        <div id="editor-surface"
                             class="border rounded-3 p-4 bg-white"
                             style="min-height:60vh; max-height:78vh; overflow-y:auto; line-height:2;"></div>

                        <div class="d-flex justify-content-between text-muted small mt-2">
                            <span id="word-count">0 words</span>
                            <span id="char-count">0 characters</span>
                        </div>
                    </div>
                </div>

                {{-- Version history --}}
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">
                                <i class="bi bi-clock-history me-2 text-primary"></i>Version history
                                <span class="badge text-bg-light ms-1" id="version-count">0</span>
                            </h6>
                            @if($capabilities['canEdit'])
                                <div class="input-group input-group-sm" style="max-width:22rem;">
                                    <input id="version-label" type="text" class="form-control" placeholder="Label this version, e.g. 'After methods rewrite'">
                                    <button id="btn-save-version" class="btn btn-outline-primary" type="button">Save version</button>
                                </div>
                            @endif
                        </div>

                        <p class="text-muted small">
                            A version is recorded on import, on an explicit save, and whenever you restore one.
                            Restoring never deletes history — it creates a new revision.
                        </p>

                        <div id="versions-list"></div>

                        <div class="d-flex gap-2 align-items-center mt-3">
                            <button id="btn-compare" class="btn btn-outline-secondary btn-sm">Compare A and B</button>
                            <span id="diff-summary" class="text-muted small"></span>
                        </div>

                        <div id="diff-view" class="border rounded-3 p-3 mt-2 bg-light small"
                             style="max-height:22vh; overflow-y:auto; line-height:1.9;"></div>
                    </div>
                </div>
            </div>

            {{-- Side panels --}}
            <div class="col-lg-5 col-xl-4">

                {{-- Comments --}}
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-chat-left-text me-2 text-primary"></i>Comments</h6>

                        @if($capabilities['canComment'])
                            <textarea id="comment-body" class="form-control form-control-sm mb-2" rows="3"
                                      placeholder="Select text in the manuscript, then write your comment here."></textarea>
                            <div class="d-flex gap-2 mb-3">
                                <select id="comment-visibility" class="form-select form-select-sm">
                                    @foreach($commentVisibilities as $visibility)
                                        <option value="{{ $visibility }}">
                                            {{ ucwords(str_replace('_', ' ', $visibility)) }}
                                        </option>
                                    @endforeach
                                </select>
                                <button id="btn-comment-post" class="btn btn-primary btn-sm text-nowrap">Post</button>
                            </div>
                        @else
                            <p class="text-muted small">You do not have permission to comment on this manuscript.</p>
                        @endif

                        <div id="comment-list"></div>
                    </div>
                </div>

                {{-- Tracked changes --}}
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-pencil-square me-2 text-primary"></i>Tracked changes
                            <span class="badge text-bg-warning ms-1" id="pending-count">{{ $pendingChangeCount }}</span>
                        </h6>

                        <p class="text-muted small">
                            Editing is sequential, not real-time. Anyone editing at the same time is detected and
                            nothing is overwritten — you will be asked which version to keep.
                        </p>

                        <div id="changes-list"></div>

                        <div id="bulk-actions" class="d-none btn-group btn-group-sm mt-2">
                            <button id="bulk-accept" class="btn btn-outline-success">Accept all</button>
                            <button id="bulk-reject" class="btn btn-outline-danger">Reject all</button>
                        </div>
                    </div>
                </div>

                {{-- Editorial context --}}
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Manuscript details</h6>
                        <dl class="row small mb-0">
                            <dt class="col-5 text-muted">Submission</dt>
                            <dd class="col-7">{{ $submission->submission_no }}</dd>

                            <dt class="col-5 text-muted">Status</dt>
                            <dd class="col-7">{{ ucfirst($submission->status) }}</dd>

                            <dt class="col-5 text-muted">Submitted by</dt>
                            <dd class="col-7">{{ $submission->author?->name }}</dd>

                            <dt class="col-5 text-muted">Files</dt>
                            <dd class="col-7">{{ $submission->files->count() }}</dd>

                            <dt class="col-5 text-muted">Assigned editor</dt>
                            <dd class="col-7">{{ $submission->assignedEditor?->name ?? 'Not yet assigned' }}</dd>

                            <dt class="col-5 text-muted">Vetting fee</dt>
                            <dd class="col-7">{{ $submission->hasPaidVettingFee() ? 'Paid' : 'Outstanding' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Conflict resolution --}}
    <div class="modal fade" id="conflict-modal" tabindex="-1" aria-hidden="true" aria-labelledby="conflict-title">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="conflict-title">
                        <i class="bi bi-exclamation-triangle me-2 text-warning"></i>Someone else saved first
                    </h5>
                </div>
                <div class="modal-body">
                    <p id="conflict-message"></p>
                    <p id="conflict-detail" class="small text-muted"></p>

                    <div class="alert alert-info small rounded-3">
                        <strong>Nothing has been lost.</strong> Your text and the other editor's text are both
                        still available. Choose how you would like to continue.
                    </div>

                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" data-conflict-strategy="mine">
                            Keep my version and overwrite theirs
                        </button>
                        <button class="btn btn-outline-secondary" data-conflict-strategy="theirs">
                            Take their version and discard mine
                        </button>
                        <button class="btn btn-outline-primary" data-conflict-strategy="both">
                            Keep both in the document so I can reconcile them myself
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="module" src="{{ Vite::asset('resources/js/workspace/index.js') }}"></script>
@endpush

@push('styles')
<style>
    /* Typography mirrors the APA 7 manuscript by default (12 pt serif, double
       spacing, black on white, flush left); explicit paragraph attributes
       chosen in the toolbar override these and are exported faithfully. */
    #editor-surface .tiptap { outline: none; min-height: 55vh; font-family: 'Times New Roman', Cambria, 'Liberation Serif', 'Nimbus Roman', serif; font-size: 12pt; color: #111; }
    /* Paragraph spacing mirrors the APA 7 default of 0 pt before / 0 pt after
       (the visual gaps Word's styles add are gone); the editor can add gaps
       with the Before/After controls, which write inline styles that win. */
    #editor-surface .tiptap p { margin: 0; text-indent: 36pt; }
    #editor-surface .tiptap blockquote p { text-indent: 0; }
    #editor-surface .tiptap h1,
    #editor-surface .tiptap h2,
    #editor-surface .tiptap h3 { margin: 1.4rem 0 .7rem 0; font-weight: 600; }
    #editor-surface .tiptap blockquote { border-left: 3px solid #cbd5e1; padding-left: 1rem; color: #64748b; }

    .oj-track-insert { text-decoration: underline; color: #15803d; text-decoration-thickness: 2px; }
    .oj-track-delete { text-decoration: line-through; color: #b91c1c; background: #fee2e2; }
    .oj-comment-anchor { background: #fef3c7; border-bottom: 2px solid #f59e0b; }
    .oj-placeholder { color: #94a3b8; }
    .is-editor-empty:first-child::before {
        content: attr(data-placeholder);
        float: left; height: 0; pointer-events: none; color: #94a3b8;
    }
</style>
@endpush